<?php

class TelebotStorage
{
    private static $path = '';
    private static $pdo = null;

    public static function configure($path = '')
    {
        self::$path = $path ?: dirname(__DIR__).'/data/telebot.sqlite';
        self::db();
    }

    public static function path()
    {
        if (self::$path == '') {
            self::$path = dirname(__DIR__).'/data/telebot.sqlite';
        }

        return self::$path;
    }

    public static function db()
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        if (!class_exists('PDO')) {
            throw new Exception('PDO is required for SQLite storage.');
        }

        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        self::$pdo = new PDO('sqlite:'.$path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->exec('PRAGMA journal_mode = WAL');
        self::$pdo->exec('PRAGMA synchronous = NORMAL');
        self::migrate();

        return self::$pdo;
    }

    public static function logUpdate($update)
    {
        $pdo = self::db();
        $message = self::messageFromUpdate($update);
        $chat = isset($message['chat']) ? $message['chat'] : [];
        $from = isset($message['from']) ? $message['from'] : self::fromUpdate($update);
        $text = self::messageText($message);
        $date = isset($message['date']) ? $message['date'] : time();
        $updateType = self::updateType($update);
        $messageType = self::messageType($message, $updateType);
        $chatId = isset($chat['id']) ? $chat['id'] : null;
        $topicId = isset($message['message_thread_id']) ? $message['message_thread_id'] : null;
        $messageId = isset($message['message_id']) ? $message['message_id'] : null;
        $replyToMessageId = isset($message['reply_to_message']['message_id']) ? $message['reply_to_message']['message_id'] : null;

        if ($chatId !== null) {
            self::upsertChat($chat, $date);
            self::upsertTopic($chatId, $topicId, $message, $date);
        }

        $stmt = $pdo->prepare('INSERT OR IGNORE INTO messages (update_id, chat_id, message_id, reply_to_message_id, topic_id, user_id, username, first_name, last_name, direction, update_type, message_type, text, payload, created_at) VALUES (:update_id, :chat_id, :message_id, :reply_to_message_id, :topic_id, :user_id, :username, :first_name, :last_name, :direction, :update_type, :message_type, :text, :payload, :created_at)');
        $stmt->execute([
            ':update_id' => isset($update['update_id']) ? $update['update_id'] : null,
            ':chat_id' => $chatId,
            ':message_id' => $messageId,
            ':reply_to_message_id' => $replyToMessageId,
            ':topic_id' => $topicId,
            ':user_id' => isset($from['id']) ? $from['id'] : null,
            ':username' => isset($from['username']) ? $from['username'] : null,
            ':first_name' => isset($from['first_name']) ? $from['first_name'] : null,
            ':last_name' => isset($from['last_name']) ? $from['last_name'] : null,
            ':direction' => 'in',
            ':update_type' => $updateType,
            ':message_type' => $messageType,
            ':text' => $text,
            ':payload' => json_encode($update),
            ':created_at' => $date,
        ]);
    }

    public static function logOutgoing($action, $request, $response)
    {
        if ($action != 'sendMessage') {
            return;
        }

        $data = json_decode($response, true);
        if (!isset($data['ok']) || !$data['ok'] || !isset($data['result'])) {
            return;
        }

        $message = $data['result'];
        $chat = isset($message['chat']) ? $message['chat'] : [];
        $chatId = isset($chat['id']) ? $chat['id'] : (isset($request['chat_id']) ? $request['chat_id'] : null);
        $topicId = isset($message['message_thread_id']) ? $message['message_thread_id'] : (isset($request['message_thread_id']) ? $request['message_thread_id'] : null);
        $messageId = isset($message['message_id']) ? $message['message_id'] : null;
        $replyToMessageId = isset($request['reply_parameters']['message_id']) ? $request['reply_parameters']['message_id'] : null;
        $text = isset($message['text']) ? $message['text'] : (isset($request['text']) ? $request['text'] : '');
        $date = isset($message['date']) ? $message['date'] : time();

        if ($chatId !== null && !empty($chat)) {
            self::upsertChat($chat, $date);
        }

        $stmt = self::db()->prepare('INSERT OR IGNORE INTO messages (update_id, chat_id, message_id, reply_to_message_id, topic_id, direction, update_type, message_type, text, payload, created_at) VALUES (:update_id, :chat_id, :message_id, :reply_to_message_id, :topic_id, :direction, :update_type, :message_type, :text, :payload, :created_at)');
        $stmt->execute([
            ':update_id' => null,
            ':chat_id' => $chatId,
            ':message_id' => $messageId,
            ':reply_to_message_id' => $replyToMessageId,
            ':topic_id' => $topicId,
            ':direction' => 'out',
            ':update_type' => 'bot_reply',
            ':message_type' => 'text',
            ':text' => $text,
            ':payload' => $response,
            ':created_at' => $date,
        ]);
    }

    public static function dashboard()
    {
        $pdo = self::db();

        return [
            'stats' => [
                'groups' => (int) $pdo->query("SELECT COUNT(*) FROM chats WHERE type IN ('group', 'supergroup')")->fetchColumn(),
                'private_chats' => (int) $pdo->query("SELECT COUNT(*) FROM chats WHERE type = 'private'")->fetchColumn(),
                'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
                'topics' => (int) $pdo->query('SELECT COUNT(DISTINCT chat_id || \':\' || topic_id) FROM messages WHERE topic_id IS NOT NULL')->fetchColumn(),
            ],
            'groups' => self::groups(),
            'messages' => self::messages(),
        ];
    }

    public static function groups()
    {
        $stmt = self::db()->query("SELECT * FROM chats WHERE type IN ('group', 'supergroup', 'channel') ORDER BY last_seen DESC LIMIT 200");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function messages($chatId = null)
    {
        if ($chatId) {
            $stmt = self::db()->prepare('SELECT messages.*, topics.name AS topic_name FROM messages LEFT JOIN topics ON topics.chat_id = messages.chat_id AND topics.topic_id = messages.topic_id WHERE messages.chat_id = :chat_id ORDER BY messages.id DESC LIMIT 100');
            $stmt->execute([':chat_id' => $chatId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = self::db()->query('SELECT messages.*, topics.name AS topic_name FROM messages LEFT JOIN topics ON topics.chat_id = messages.chat_id AND topics.topic_id = messages.topic_id ORDER BY messages.id DESC LIMIT 100');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function replies($limit = 100)
    {
        $stmt = self::db()->prepare('SELECT * FROM replies ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveReply($chatId, $topicId, $messageId, $text, $response)
    {
        $stmt = self::db()->prepare('INSERT INTO replies (chat_id, topic_id, reply_to_message_id, text, response, created_at) VALUES (:chat_id, :topic_id, :reply_to_message_id, :text, :response, :created_at)');
        $stmt->execute([
            ':chat_id' => $chatId,
            ':topic_id' => $topicId ?: null,
            ':reply_to_message_id' => $messageId ?: null,
            ':text' => $text,
            ':response' => $response,
            ':created_at' => time(),
        ]);
    }

    private static function migrate()
    {
        $pdo = self::$pdo;
        $pdo->exec('CREATE TABLE IF NOT EXISTS chats (chat_id INTEGER PRIMARY KEY, type TEXT, title TEXT, username TEXT, first_name TEXT, last_name TEXT, raw TEXT, first_seen INTEGER, last_seen INTEGER)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, update_id INTEGER UNIQUE, chat_id INTEGER, message_id INTEGER, reply_to_message_id INTEGER, topic_id INTEGER, user_id INTEGER, username TEXT, first_name TEXT, last_name TEXT, direction TEXT DEFAULT \'in\', update_type TEXT, message_type TEXT, text TEXT, payload TEXT, created_at INTEGER)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS replies (id INTEGER PRIMARY KEY AUTOINCREMENT, chat_id INTEGER, topic_id INTEGER, reply_to_message_id INTEGER, text TEXT, response TEXT, created_at INTEGER)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS topics (chat_id INTEGER, topic_id INTEGER, name TEXT, icon_color INTEGER, raw TEXT, first_seen INTEGER, last_seen INTEGER, PRIMARY KEY (chat_id, topic_id))');
        self::addColumn('messages', 'reply_to_message_id', 'INTEGER');
        self::addColumn('messages', 'direction', 'TEXT DEFAULT \'in\'');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_chat_created ON messages(chat_id, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_topic ON messages(chat_id, topic_id)');
    }

    private static function addColumn($table, $column, $definition)
    {
        try {
            self::$pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$definition);
        } catch (Exception $e) {
        }
    }

    private static function upsertChat($chat, $date)
    {
        $chatId = isset($chat['id']) ? $chat['id'] : null;
        $exists = self::db()->prepare('SELECT chat_id FROM chats WHERE chat_id = :chat_id');
        $exists->execute([':chat_id' => $chatId]);

        if ($exists->fetchColumn()) {
            $stmt = self::db()->prepare('UPDATE chats SET type = :type, title = :title, username = :username, first_name = :first_name, last_name = :last_name, raw = :raw, last_seen = :last_seen WHERE chat_id = :chat_id');
            $stmt->execute([
                ':chat_id' => $chatId,
                ':type' => isset($chat['type']) ? $chat['type'] : null,
                ':title' => isset($chat['title']) ? $chat['title'] : null,
                ':username' => isset($chat['username']) ? $chat['username'] : null,
                ':first_name' => isset($chat['first_name']) ? $chat['first_name'] : null,
                ':last_name' => isset($chat['last_name']) ? $chat['last_name'] : null,
                ':raw' => json_encode($chat),
                ':last_seen' => $date,
            ]);

            return;
        }

        $stmt = self::db()->prepare('INSERT INTO chats (chat_id, type, title, username, first_name, last_name, raw, first_seen, last_seen) VALUES (:chat_id, :type, :title, :username, :first_name, :last_name, :raw, :first_seen, :last_seen)');
        $stmt->execute([
            ':chat_id' => $chatId,
            ':type' => isset($chat['type']) ? $chat['type'] : null,
            ':title' => isset($chat['title']) ? $chat['title'] : null,
            ':username' => isset($chat['username']) ? $chat['username'] : null,
            ':first_name' => isset($chat['first_name']) ? $chat['first_name'] : null,
            ':last_name' => isset($chat['last_name']) ? $chat['last_name'] : null,
            ':raw' => json_encode($chat),
            ':first_seen' => $date,
            ':last_seen' => $date,
        ]);
    }

    private static function upsertTopic($chatId, $topicId, $message, $date)
    {
        if ($topicId === null) {
            return;
        }

        $topic = null;
        if (isset($message['forum_topic_created'])) {
            $topic = $message['forum_topic_created'];
        } elseif (isset($message['forum_topic_edited'])) {
            $topic = $message['forum_topic_edited'];
        }

        $name = isset($topic['name']) ? $topic['name'] : null;
        $iconColor = isset($topic['icon_color']) ? $topic['icon_color'] : null;
        $exists = self::db()->prepare('SELECT topic_id FROM topics WHERE chat_id = :chat_id AND topic_id = :topic_id');
        $exists->execute([':chat_id' => $chatId, ':topic_id' => $topicId]);

        if ($exists->fetchColumn()) {
            if ($name === null) {
                $stmt = self::db()->prepare('UPDATE topics SET last_seen = :last_seen WHERE chat_id = :chat_id AND topic_id = :topic_id');
                $stmt->execute([':chat_id' => $chatId, ':topic_id' => $topicId, ':last_seen' => $date]);

                return;
            }

            $stmt = self::db()->prepare('UPDATE topics SET name = :name, icon_color = :icon_color, raw = :raw, last_seen = :last_seen WHERE chat_id = :chat_id AND topic_id = :topic_id');
            $stmt->execute([':chat_id' => $chatId, ':topic_id' => $topicId, ':name' => $name, ':icon_color' => $iconColor, ':raw' => json_encode($topic), ':last_seen' => $date]);

            return;
        }

        $stmt = self::db()->prepare('INSERT INTO topics (chat_id, topic_id, name, icon_color, raw, first_seen, last_seen) VALUES (:chat_id, :topic_id, :name, :icon_color, :raw, :first_seen, :last_seen)');
        $stmt->execute([':chat_id' => $chatId, ':topic_id' => $topicId, ':name' => $name, ':icon_color' => $iconColor, ':raw' => json_encode($topic), ':first_seen' => $date, ':last_seen' => $date]);
    }

    private static function messageFromUpdate($update)
    {
        foreach (['message', 'business_message', 'guest_message', 'edited_message', 'channel_post', 'edited_channel_post', 'edited_business_message'] as $field) {
            if (isset($update[$field])) {
                return $update[$field];
            }
        }

        if (isset($update['callback_query']['message'])) {
            return $update['callback_query']['message'];
        }

        return [];
    }

    private static function fromUpdate($update)
    {
        foreach ($update as $value) {
            if (is_array($value) && isset($value['from'])) {
                return $value['from'];
            }
        }

        return [];
    }

    private static function updateType($update)
    {
        foreach ($update as $key => $value) {
            if ($key != 'update_id') {
                return $key;
            }
        }

        return 'unknown';
    }

    private static function messageType($message, $fallback)
    {
        foreach (['text', 'photo', 'document', 'video', 'voice', 'audio', 'sticker', 'animation', 'poll', 'location', 'contact', 'new_chat_members', 'left_chat_member', 'forum_topic_created', 'forum_topic_edited', 'forum_topic_closed', 'forum_topic_reopened'] as $field) {
            if (isset($message[$field])) {
                return $field;
            }
        }

        return $fallback;
    }

    private static function messageText($message)
    {
        foreach (['text', 'caption'] as $field) {
            if (isset($message[$field])) {
                return $message[$field];
            }
        }

        return '';
    }
}
