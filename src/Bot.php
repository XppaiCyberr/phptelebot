<?php
/**
 * Bot.php.
 *
 *
 * @author Radya <radya@gmx.com>
 *
 * @link https://github.com/radyakaze/phptelebot
 *
 * @license GPL-3.0
 */

/**
 * Class Bot.
 */
class Bot
{
    /**
     * Bot response debug.
     * 
     * @var string
     */
    public static $debug = '';

    /**
     * Send request to telegram api server.
     *
     * @param string $action
     * @param array  $data   [optional]
     *
     * @return array|bool
     */
    public static function send($action = 'sendMessage', $data = [])
    {
        $upload = false;
        $actionUpload = ['sendPhoto', 'sendAudio', 'sendDocument', 'sendSticker', 'sendVideo', 'sendVoice', 'sendVideoNote', 'sendAnimation', 'sendPaidMedia'];

        if (in_array($action, $actionUpload)) {
            $field = str_replace('send', '', strtolower($action));
            if ($field === 'paidmedia') {
                $field = 'media';
            }

            if (isset($data[$field]) && is_file($data[$field])) {
                $upload = true;
                $data[$field] = self::curlFile($data[$field]);
            }
        }

        $needChatId = ['sendMessage', 'forwardMessage', 'sendPhoto', 'sendAudio', 'sendDocument', 'sendSticker', 'sendVideo', 'sendVoice', 'sendVideoNote', 'sendAnimation', 'sendLocation', 'sendVenue', 'sendContact', 'sendChatAction', 'editMessageText', 'editMessageCaption', 'editMessageReplyMarkup', 'sendGame', 'sendPoll', 'sendDice', 'sendPaidMedia', 'sendGift', 'refundStarPayment'];
        if (in_array($action, $needChatId) && !isset($data['chat_id'])) {
            $getUpdates = PHPTelebot::$getUpdates;
            if (isset($getUpdates['callback_query'])) {
                $getUpdates = $getUpdates['callback_query'];
            } elseif (isset($getUpdates['business_message'])) {
                $getUpdates['message'] = $getUpdates['business_message'];
            }
            $data['chat_id'] = $getUpdates['message']['chat']['id'];
            // Reply message
            if (!isset($data['reply_to_message_id']) && isset($data['reply']) && $data['reply'] === true) {
                $data['reply_to_message_id'] = $getUpdates['message']['message_id'];
                unset($data['reply']);
            }
        }

        if (isset($data['reply_markup']) && is_array($data['reply_markup'])) {
            $data['reply_markup'] = json_encode($data['reply_markup']);
        }

        $ch = curl_init();
        $options = [
            CURLOPT_URL => 'https://api.telegram.org/bot'.PHPTelebot::$token.'/'.$action,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        if (is_array($data)) {
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        if ($upload !== false) {
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: multipart/form-data'];
        }

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            echo curl_error($ch)."\n";
        }
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (PHPTelebot::$debug && $action != 'getUpdates') {
            self::$debug .= 'Method: '.$action."\n";
            self::$debug .= 'Data: '.str_replace("Array\n", '', print_r($data, true))."\n";
            self::$debug .= 'Response: '.$result."\n";
        }

        if ($httpcode == 401) {
            throw new Exception('Incorect bot token');

            return false;
        } else {
            return $result;
        }
    }

    /**
     * Answer Inline.
     *
     * @param array $results
     * @param array $options
     *
     * @return string
     */
    public static function answerInlineQuery($results, $options = [])
    {
        if (!empty($options)) {
            $data = $options;
        }

        if (!isset($options['inline_query_id'])) {
            $get = PHPTelebot::$getUpdates;
            $data['inline_query_id'] = $get['inline_query']['id'];
        }

        $data['results'] = json_encode($results);

        return self::send('answerInlineQuery', $data);
    }

    /**
     * Answer Callback.
     *
     * @param string $text
     * @param array  $options [optional]
     *
     * @return string
     */
    public static function answerCallbackQuery($text, $options = [])
    {
        $options['text'] = $text;

        if (!isset($options['callback_query_id'])) {
            $get = PHPTelebot::$getUpdates;
            $options['callback_query_id'] = $get['callback_query']['id'];
        }

        return self::send('answerCallbackQuery', $options);
    }

    /**
     * Answer Web App Query.
     *
     * @param array $result
     * @param array $options [optional]
     *
     * @return string
     */
    public static function answerWebAppQuery($result, $options = [])
    {
        if (!isset($options['web_app_query_id'])) {
            $get = PHPTelebot::$getUpdates;
            $options['web_app_query_id'] = $get['web_app_query']['query_id'];
        }

        $options['result'] = json_encode($result);

        return self::send('answerWebAppQuery', $options);
    }

    /**
     * Create curl file.
     *
     * @param string $path
     *
     * @return string
     */
    private static function curlFile($path)
    {
        // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
        // See: https://wiki.php.net/rfc/curl-file-upload
        if (function_exists('curl_file_create')) {
            return curl_file_create($path);
        } else {
            // Use the old style if using an older version of PHP
            return "@$path";
        }
    }

    /**
     * Get message properties.
     *
     * @return array
     */
    public static function message()
    {
        $get = PHPTelebot::$getUpdates;
        if (isset($get['message'])) {
            return $get['message'];
        } elseif (isset($get['business_message'])) {
            return $get['business_message'];
        } elseif (isset($get['callback_query'])) {
            return $get['callback_query'];
        } elseif (isset($get['inline_query'])) {
            return $get['inline_query'];
        } elseif (isset($get['edited_message'])) {
            return $get['edited_message'];
        } elseif (isset($get['edited_business_message'])) {
            return $get['edited_business_message'];
        } elseif (isset($get['channel_post'])) {
            return $get['channel_post'];
        } elseif (isset($get['edited_channel_post'])) {
            return $get['edited_channel_post'];
        } else {
            return [];
        }
    }

    /**
     * Message type.
     *
     * @return string
     */
    public static function type()
    {
        $getUpdates = PHPTelebot::$getUpdates;

        // Business account updates
        if (isset($getUpdates['business_connection'])) {
            return 'business_connection';
        } elseif (isset($getUpdates['business_message'])) {
            return 'business_message';
        } elseif (isset($getUpdates['edited_business_message'])) {
            return 'edited_business_message';
        } elseif (isset($getUpdates['deleted_business_messages'])) {
            return 'deleted_business_messages';
        }

        // Regular message types
        if (isset($getUpdates['message']['text'])) {
            return 'text';
        } elseif (isset($getUpdates['message']['photo'])) {
            return 'photo';
        } elseif (isset($getUpdates['message']['video'])) {
            return 'video';
        } elseif (isset($getUpdates['message']['video_note'])) {
            return 'video_note';
        } elseif (isset($getUpdates['message']['animation'])) {
            return 'animation';
        } elseif (isset($getUpdates['message']['audio'])) {
            return 'audio';
        } elseif (isset($getUpdates['message']['voice'])) {
            return 'voice';
        } elseif (isset($getUpdates['message']['document'])) {
            return 'document';
        } elseif (isset($getUpdates['message']['sticker'])) {
            return 'sticker';
        } elseif (isset($getUpdates['message']['venue'])) {
            return 'venue';
        } elseif (isset($getUpdates['message']['location'])) {
            return 'location';
        } elseif (isset($getUpdates['message']['contact'])) {
            return 'contact';
        } elseif (isset($getUpdates['message']['poll'])) {
            return 'poll';
        } elseif (isset($getUpdates['message']['dice'])) {
            return 'dice';
        } elseif (isset($getUpdates['message']['game'])) {
            return 'game';
        } elseif (isset($getUpdates['message']['paid_media'])) {
            return 'paid_media';
        } elseif (isset($getUpdates['message']['gift']) || isset($getUpdates['message']['unique_gift'])) {
            return 'gift';
        } elseif (isset($getUpdates['message']['paid_message_price_changed'])) {
            return 'paid_message_price_changed';
        }

        // Chat events
        elseif (isset($getUpdates['message']['new_chat_members'])) {
            return 'new_chat_member';
        } elseif (isset($getUpdates['message']['left_chat_member'])) {
            return 'left_chat_member';
        } elseif (isset($getUpdates['message']['new_chat_title'])) {
            return 'new_chat_title';
        } elseif (isset($getUpdates['message']['new_chat_photo'])) {
            return 'new_chat_photo';
        } elseif (isset($getUpdates['message']['delete_chat_photo'])) {
            return 'delete_chat_photo';
        } elseif (isset($getUpdates['message']['group_chat_created'])) {
            return 'group_chat_created';
        } elseif (isset($getUpdates['message']['supergroup_chat_created'])) {
            return 'supergroup_chat_created';
        } elseif (isset($getUpdates['message']['channel_chat_created'])) {
            return 'channel_chat_created';
        } elseif (isset($getUpdates['message']['migrate_to_chat_id'])) {
            return 'migrate_to_chat_id';
        } elseif (isset($getUpdates['message']['migrate_from_chat_id'])) {
            return 'migrate_from_chat_id';
        } elseif (isset($getUpdates['message']['pinned_message'])) {
            return 'pinned_message';
        } elseif (isset($getUpdates['message']['invoice'])) {
            return 'invoice';
        } elseif (isset($getUpdates['message']['successful_payment'])) {
            return 'successful_payment';
        } elseif (isset($getUpdates['message']['refunded_payment'])) {
            return 'refunded_payment';
        } elseif (isset($getUpdates['message']['users_shared'])) {
            return 'users_shared';
        } elseif (isset($getUpdates['message']['chat_shared'])) {
            return 'chat_shared';
        } elseif (isset($getUpdates['message']['connected_website'])) {
            return 'connected_website';
        } elseif (isset($getUpdates['message']['write_access_allowed'])) {
            return 'write_access_allowed';
        } elseif (isset($getUpdates['message']['passport_data'])) {
            return 'passport_data';
        } elseif (isset($getUpdates['message']['proximity_alert_triggered'])) {
            return 'proximity_alert_triggered';
        } elseif (isset($getUpdates['message']['boost_added'])) {
            return 'boost_added';
        } elseif (isset($getUpdates['message']['chat_background_set'])) {
            return 'chat_background_set';
        } elseif (isset($getUpdates['message']['forum_topic_created'])) {
            return 'forum_topic_created';
        } elseif (isset($getUpdates['message']['forum_topic_edited'])) {
            return 'forum_topic_edited';
        } elseif (isset($getUpdates['message']['forum_topic_closed'])) {
            return 'forum_topic_closed';
        } elseif (isset($getUpdates['message']['forum_topic_reopened'])) {
            return 'forum_topic_reopened';
        } elseif (isset($getUpdates['message']['general_forum_topic_hidden'])) {
            return 'general_forum_topic_hidden';
        } elseif (isset($getUpdates['message']['general_forum_topic_unhidden'])) {
            return 'general_forum_topic_unhidden';
        } elseif (isset($getUpdates['message']['giveaway_created'])) {
            return 'giveaway_created';
        } elseif (isset($getUpdates['message']['giveaway'])) {
            return 'giveaway';
        } elseif (isset($getUpdates['message']['giveaway_winners'])) {
            return 'giveaway_winners';
        } elseif (isset($getUpdates['message']['giveaway_completed'])) {
            return 'giveaway_completed';
        } elseif (isset($getUpdates['message']['video_chat_scheduled'])) {
            return 'video_chat_scheduled';
        } elseif (isset($getUpdates['message']['video_chat_started'])) {
            return 'video_chat_started';
        } elseif (isset($getUpdates['message']['video_chat_ended'])) {
            return 'video_chat_ended';
        } elseif (isset($getUpdates['message']['video_chat_participants_invited'])) {
            return 'video_chat_participants_invited';
        } elseif (isset($getUpdates['message']['web_app_data'])) {
            return 'web_app_data';
        }

        // Other update types
        elseif (isset($getUpdates['inline_query'])) {
            return 'inline';
        } elseif (isset($getUpdates['chosen_inline_result'])) {
            return 'chosen_inline_result';
        } elseif (isset($getUpdates['callback_query'])) {
            return 'callback';
        } elseif (isset($getUpdates['shipping_query'])) {
            return 'shipping_query';
        } elseif (isset($getUpdates['pre_checkout_query'])) {
            return 'pre_checkout_query';
        } elseif (isset($getUpdates['poll'])) {
            return 'poll_update';
        } elseif (isset($getUpdates['poll_answer'])) {
            return 'poll_answer';
        } elseif (isset($getUpdates['my_chat_member'])) {
            return 'my_chat_member';
        } elseif (isset($getUpdates['chat_member'])) {
            return 'chat_member';
        } elseif (isset($getUpdates['chat_join_request'])) {
            return 'chat_join_request';
        } elseif (isset($getUpdates['chat_boost'])) {
            return 'chat_boost';
        } elseif (isset($getUpdates['removed_chat_boost'])) {
            return 'removed_chat_boost';
        } elseif (isset($getUpdates['message_reaction'])) {
            return 'message_reaction';
        } elseif (isset($getUpdates['message_reaction_count'])) {
            return 'message_reaction_count';
        } elseif (isset($getUpdates['edited_message'])) {
            return 'edited';
        } elseif (isset($getUpdates['channel_post'])) {
            return 'channel';
        } elseif (isset($getUpdates['edited_channel_post'])) {
            return 'edited_channel';
        } else {
            return 'unknown';
        }
    }

    /**
     * Create an action.
     *
     * @param string $name
     * @param array  $args
     *
     * @return array
     */
    public static function __callStatic($action, $args)
    {
        $param = [];
        $firstParam = [
            'sendMessage' => 'text',
            'sendPhoto' => 'photo',
            'sendVideo' => 'video',
            'sendVideoNote' => 'video_note',
            'sendAnimation' => 'animation',
            'sendAudio' => 'audio',
            'sendVoice' => 'voice',
            'sendDocument' => 'document',
            'sendSticker' => 'sticker',
            'sendVenue' => 'venue',
            'sendLocation' => 'location',
            'sendContact' => 'contact',
            'sendPoll' => 'question',
            'sendDice' => 'emoji',
            'sendChatAction' => 'action',
            'sendPaidMedia' => 'star_count',
            'sendGift' => 'gift_id',
            'setWebhook' => 'url',
            'getUserProfilePhotos' => 'user_id',
            'getFile' => 'file_id',
            'getChat' => 'chat_id',
            'leaveChat' => 'chat_id',
            'getChatAdministrators' => 'chat_id',
            'getChatMembersCount' => 'chat_id',
            'getChatMemberCount' => 'chat_id',
            'getChatMember' => 'chat_id',
            'setChatStickerSet' => 'chat_id',
            'deleteChatStickerSet' => 'chat_id',
            'getForumTopicIconStickers' => '',
            'createForumTopic' => 'chat_id',
            'editForumTopic' => 'chat_id',
            'closeForumTopic' => 'chat_id',
            'reopenForumTopic' => 'chat_id',
            'deleteForumTopic' => 'chat_id',
            'unpinAllForumTopicMessages' => 'chat_id',
            'editGeneralForumTopic' => 'chat_id',
            'closeGeneralForumTopic' => 'chat_id',
            'reopenGeneralForumTopic' => 'chat_id',
            'hideGeneralForumTopic' => 'chat_id',
            'unhideGeneralForumTopic' => 'chat_id',
            'unpinAllGeneralForumTopicMessages' => 'chat_id',
            'answerCallbackQuery' => 'callback_query_id',
            'setMyCommands' => 'commands',
            'deleteMyCommands' => '',
            'getMyCommands' => '',
            'setMyName' => '',
            'getMyName' => '',
            'setMyDescription' => '',
            'getMyDescription' => '',
            'setMyShortDescription' => '',
            'getMyShortDescription' => '',
            'setChatMenuButton' => '',
            'getChatMenuButton' => '',
            'setMyDefaultAdministratorRights' => '',
            'getMyDefaultAdministratorRights' => '',
            'editMessageText' => 'text',
            'editMessageCaption' => '',
            'editMessageMedia' => 'media',
            'editMessageReplyMarkup' => '',
            'stopPoll' => 'chat_id',
            'deleteMessage' => 'chat_id',
            'deleteMessages' => 'chat_id',
            'sendGame' => 'game_short_name',
            'setGameScore' => 'user_id',
            'getGameHighScores' => 'user_id',
            'sendInvoice' => 'title',
            'createInvoiceLink' => 'title',
            'answerShippingQuery' => 'shipping_query_id',
            'answerPreCheckoutQuery' => 'pre_checkout_query_id',
            'refundStarPayment' => 'user_id',
            'getStarTransactions' => '',
            'setPassportDataErrors' => 'user_id',
            'sendSticker' => 'sticker',
            'getStickerSet' => 'name',
            'getCustomEmojiStickers' => 'custom_emoji_ids',
            'uploadStickerFile' => 'user_id',
            'createNewStickerSet' => 'user_id',
            'addStickerToSet' => 'user_id',
            'setStickerPositionInSet' => 'sticker',
            'deleteStickerFromSet' => 'sticker',
            'replaceStickerInSet' => 'user_id',
            'setStickerEmojiList' => 'sticker',
            'setStickerKeywords' => 'sticker',
            'setStickerMaskPosition' => 'sticker',
            'setStickerSetTitle' => 'name',
            'setStickerSetThumbnail' => 'name',
            'setCustomEmojiStickerSetThumbnail' => 'name',
            'deleteStickerSet' => 'name',
            'answerInlineQuery' => 'inline_query_id',
            'answerWebAppQuery' => 'web_app_query_id',
            'sendChatAction' => 'action',
            'banChatMember' => 'chat_id',
            'unbanChatMember' => 'chat_id',
            'restrictChatMember' => 'chat_id',
            'promoteChatMember' => 'chat_id',
            'setChatAdministratorCustomTitle' => 'chat_id',
            'banChatSenderChat' => 'chat_id',
            'unbanChatSenderChat' => 'chat_id',
            'setChatPermissions' => 'chat_id',
            'exportChatInviteLink' => 'chat_id',
            'createChatInviteLink' => 'chat_id',
            'editChatInviteLink' => 'chat_id',
            'revokeChatInviteLink' => 'chat_id',
            'approveChatJoinRequest' => 'chat_id',
            'declineChatJoinRequest' => 'chat_id',
            'setChatPhoto' => 'chat_id',
            'deleteChatPhoto' => 'chat_id',
            'setChatTitle' => 'chat_id',
            'setChatDescription' => 'chat_id',
            'pinChatMessage' => 'chat_id',
            'unpinChatMessage' => 'chat_id',
            'unpinAllChatMessages' => 'chat_id',
            'getBusinessConnection' => 'business_connection_id',
        ];

        if (!isset($firstParam[$action])) {
            if (isset($args[0]) && is_array($args[0])) {
                $param = $args[0];
            }
        } else {
            if ($firstParam[$action] !== '') {
                $param[$firstParam[$action]] = $args[0];
            }
            if (isset($args[1]) && is_array($args[1])) {
                $param = array_merge($param, $args[1]);
            }
        }

        return call_user_func_array('self::send', [$action, $param]);
    }
}
