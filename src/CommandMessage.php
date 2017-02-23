<?php
/**
 * Created by PhpStorm.
 * User: szj
 * Date: 16/12/1
 * Time: 14:53
 */

namespace Jezzis\MysqlSyncer;


use cogpowered\FineDiff\Diff;

class CommandMessage
{
    protected $msgList;

    protected $levels = [32, 64, 128, 256];

    const MSG_LEVEL_NORMAL = 32;
    const MSG_LEVEL_VERBOSE = 64;
    const MSG_LEVEL_VVERBOSE = 128;
    const MSG_LEVEL_DEBUG = 256;

    protected $styles = [
        'comment',
        'error',
        'info',
        ''
    ];
    const MSG_STYLE_COMMENT = 'comment';
    const MSG_STYLE_ERROR = 'error';
    const MSG_STYLE_INFO = 'info';
    const MSG_STYLE_NONE = '';

    public function __construct()
    {
        $this->flush();
    }

    public function flush()
    {
        unset($this->msgList);
        $this->msgList = [];
    }

    public function warning($message, $style = CommandMessage::MSG_STYLE_INFO)
    {
        $this->append($message, self::MSG_LEVEL_NORMAL, $style);
    }

    public function verbose($message, $style = CommandMessage::MSG_STYLE_COMMENT)
    {
        $this->append($message, self::MSG_LEVEL_VERBOSE, $style);
    }

    public function vverbose($message, $style = CommandMessage::MSG_STYLE_COMMENT)
    {
        $this->append($message, self::MSG_LEVEL_VVERBOSE, $style);
    }

    public function vvverbose($message, $style = CommandMessage::MSG_STYLE_COMMENT)
    {
        $this->debug($message, $style);
    }

    public function debug($message, $style = CommandMessage::MSG_STYLE_COMMENT)
    {
        $this->append($message, self::MSG_LEVEL_DEBUG, $style);
    }


    public function append($message, $level = CommandMessage::MSG_LEVEL_DEBUG, $style = CommandMessage::MSG_STYLE_NONE)
    {
        if (!in_array($level, $this->levels)) {
            return ;
        }

        if (!in_array($style, $this->styles)) {
            return ;
        }

        $this->msgList[] = [$message, $level, $style];
    }

    public function get()
    {
        $msgList = $this->msgList;
        $this->flush();
        return $msgList;
    }

    public function highlightDiff($str1, $str2)
    {
        static $diff = null;
        if (empty($diff)) {
            $diff = new Diff();
        }
        $token = $diff->render($str1, $str2);
        $token = preg_replace("/<del>((?:.|\s)+?)<\/del>/", "<error>$1</error>", $token);
        $token = preg_replace("/<ins>((?:.|\s)+?)<\/ins>/", "<question>$1</question>", $token);
        return $token;
    }
}