<?php
/**
 * PHPTelebot.php.
 *
 *
 * @author Radya <radya@gmx.com>
 *
 * @link https://github.com/radyakaze/phptelebot
 *
 * @license GPL-3.0
 */

/**
 * Class PHPTelebot.
 */
class PHPTelebot
{
    /**
     * @var array
     */
    public static $getUpdates = [];
    /**
     * @var array
     */
    protected $_command = [];
    /**
     * @var array
     */
    protected $_onMessage = [];
    /**
     * @var array
     */
    protected $_options = [];
    /**
     * Bot token.
     *
     * @var string
     */
    public static $token = '';
    /**
     * Bot username.
     *
     * @var string
     */
    protected static $username = '';

    /**
     * Debug.
     *
     * @var bool
     */
    public static $debug = true;

    /**
     * PHPTelebot version.
     *
     * @var string
     */
    protected static $version = '1.4';

    /**
     * PHPTelebot Constructor.
     *
     * @param string $token
     * @param string $username
     * @param array  $options
     */
    public function __construct($token, $username = '', $options = [])
    {
        // Check php version
        if (version_compare(phpversion(), '5.4', '<')) {
            die("PHPTelebot needs to use PHP 5.4 or higher.\n");
        }

        // Check curl
        if (!function_exists('curl_version')) {
            die("cURL is NOT installed on this server.\n");
        }

        // Check bot token
        if (empty($token)) {
            die("Bot token should not be empty!\n");
        }

        self::$token = $token;
        self::$username = $username;
        $this->_options = is_array($options) ? $options : [];
        TelebotStorage::configure(isset($this->_options['database']) ? $this->_options['database'] : '');
        unset($this->_options['database']);
    }

    /**
     * Command.
     *
     * @param string          $command
     * @param callable|string $answer
     */
    public function cmd($command, $answer)
    {
        if ($command != '*') {
            $this->_command[$command] = $answer;
        }

        if (strrpos($command, '*') !== false) {
            $this->_onMessage['text'] = $answer;
        }
    }
    /**
     * Events.
     *
     * @param string          $types
     * @param callable|string $answer
     */
    public function on($types, $answer)
    {
        $types = explode('|', $types);
        foreach ($types as $type) {
            $this->_onMessage[$type] = $answer;
        }
    }

    /**
     * Custom regex for command.
     *
     * @param string          $regex
     * @param callable|string $answer
     */
    public function regex($regex, $answer)
    {
        $this->_command['customRegex:'.$regex] = $answer;
    }

    /**
     * Run telebot.
     *
     * @return bool
     */
    public function run()
    {
        try {
            if (php_sapi_name() == 'cli') {
                echo 'PHPTelebot version '.self::$version;
                echo "\nMode\t: Long Polling\n";
                $options = getopt('q', ['quiet']);
                if (isset($options['q']) || isset($options['quiet'])) {
                    self::$debug = false;
                }
                echo "Debug\t: ".(self::$debug ? 'ON' : 'OFF')."\n";
                $this->longPoll();
            } else {
                $this->webhook();
            }

            return true;
        } catch (Exception $e) {
            echo $e->getMessage()."\n";

            return false;
        }
    }

    /**
     * Webhook Mode.
     */
    private function webhook()
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && strpos($contentType, 'application/json') === 0) {
            self::$getUpdates = json_decode(file_get_contents('php://input'), true);
            echo $this->process();
        } else {
            http_response_code(400);
            throw new Exception('Access not allowed!');
        }
    }

    /**
     * Long Poll Mode.
     *
     * @throws Exception
     */
    private function longPoll()
    {
        $offset = 0;
        while (true) {
            $request = array_merge(['timeout' => 30], $this->_options, ['offset' => $offset]);
            $req = json_decode(Bot::send('getUpdates', $request), true);

            // Check error.
            if (isset($req['error_code'])) {
                if ($req['error_code'] == 404) {
                    $req['description'] = 'Incorrect bot token';
                }
                throw new Exception($req['description']);
            }

            if (!empty($req['result'])) {
                foreach ($req['result'] as $update) {
                    self::$getUpdates = $update;
                    $process = $this->process();

                    if (self::$debug) {
                        $line = "\n--------------------\n";
                        $outputFormat = "$line %s $update[update_id] $line%s";
                        echo sprintf($outputFormat, 'Query ID :', json_encode($update));
                        echo sprintf($outputFormat, 'Response for :', Bot::$debug?: $process ?: '--NO RESPONSE--');
                        // reset debug
                        Bot::$debug = '';
                    }
                    $offset = $update['update_id'] + 1;
                }
            }

            // Delay 1 second
            sleep(1);
        }
    }

    /**
     * Process the message.
     *
     * @return string
     */
    private function process()
    {
        $get = self::$getUpdates;
        TelebotStorage::logUpdate($get);
        $message = $this->currentMessage();
        $run = false;

        if (isset($message['date']) && $message['date'] < (time() - 120)) {
            return '-- Pass --';
        }

        if (Bot::type() == 'text' && isset($message['text'])) {
            foreach ($this->_command as $cmd => $call) {
                $customRegex = false;
                if (substr($cmd, 0, 12) == 'customRegex:') {
                    $regex = substr($cmd, 12);
                    // Remove bot username from command
                     if (self::$username != '') {
                         $message['text'] = preg_replace('/^\/(.*)@'.self::$username.'(.*)/', '/$1$2', $message['text']);
                     }
                    $customRegex = true;
                } else {
                    $regex = '/^(?:'.addcslashes($cmd, '/\+*?[^]$(){}=!<>:-').')'.(self::$username ? '(?:@'.self::$username.')?' : '').'(?:\s(.*))?$/';
                }
                if ($message['text'] != '*' && preg_match($regex, $message['text'], $matches)) {
                    $run = true;
                    if ($customRegex) {
                        $param = [$matches];
                    } else {
                        $param = isset($matches[1]) ? $matches[1] : '';
                    }
                    break;
                }
            }
        }

        if (isset($this->_onMessage) && $run === false) {
            $eventTypes = $this->eventTypes(Bot::type(), Bot::updateType());
            foreach ($eventTypes as $eventType) {
                if (isset($this->_onMessage[$eventType])) {
                    $run = true;
                    $call = $this->_onMessage[$eventType];
                    break;
                }
            }

            if (!$run && isset($this->_onMessage['*'])) {
                $run = true;
                $call = $this->_onMessage['*'];
            }

            if ($run) {
                switch (Bot::type()) {
                    case 'callback':
                        $param = isset($get['callback_query']['data']) ? $get['callback_query']['data'] : '';
                    break;
                    case 'inline':
                        $param = isset($get['inline_query']['query']) ? $get['inline_query']['query'] : '';
                    break;
                    case 'location':
                        $param = [$message['location']['longitude'], $message['location']['latitude']];
                    break;
                    case 'text':
                        $param = $message['text'];
                    break;
                    default:
                        if (isset($message[Bot::type()])) {
                            $param = $message[Bot::type()];
                        } elseif (isset($get[Bot::updateType()])) {
                            $param = $get[Bot::updateType()];
                        } else {
                            $param = '';
                        }
                    break;
                }
            }
        }

        if ($run) {
            if (is_callable($call)) {
                if (!is_array($param)) {
                    $count = count((new ReflectionFunction($call))->getParameters());
                    if ($count > 1) {
                        $param = array_pad(explode(' ', $param, $count), $count, '');
                    } else {
                        $param = [$param];
                    }
                }

                return call_user_func_array($call, $param);
            } else {
                if (!isset($get['inline_query'])) {
                    return Bot::send('sendMessage', ['text' => $call]);
                }
            }
        }
    }

    /**
     * Current message-like update payload.
     *
     * @return array
     */
    private function currentMessage()
    {
        $get = self::$getUpdates;
        $fields = [
            'message', 'business_message', 'guest_message', 'edited_message',
            'channel_post', 'edited_channel_post', 'edited_business_message',
        ];

        foreach ($fields as $field) {
            if (isset($get[$field])) {
                return $get[$field];
            }
        }

        if (isset($get['callback_query']['message'])) {
            return $get['callback_query']['message'];
        }

        return [];
    }

    /**
     * Candidate event names for handler matching.
     *
     * @param string $type
     * @param string $updateType
     *
     * @return array
     */
    private function eventTypes($type, $updateType)
    {
        $types = [$type, $updateType];
        $aliases = [
            'inline' => ['inline_query'],
            'inline_query' => ['inline'],
            'callback' => ['callback_query'],
            'callback_query' => ['callback'],
            'edited' => ['edited_message'],
            'edited_message' => ['edited'],
            'channel' => ['channel_post'],
            'channel_post' => ['channel'],
            'edited_channel' => ['edited_channel_post'],
            'edited_channel_post' => ['edited_channel'],
            'new_chat_members' => ['new_chat_member'],
            'new_chat_member' => ['new_chat_members'],
        ];

        foreach ($types as $candidate) {
            if (isset($aliases[$candidate])) {
                $types = array_merge($types, $aliases[$candidate]);
            }
        }

        return array_values(array_unique(array_filter($types, function ($value) {
            return $value != '' && $value != 'unknown';
        })));
    }
}

require_once __DIR__.'/Bot.php';
require_once __DIR__.'/TelebotStorage.php';
