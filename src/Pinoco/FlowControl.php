<?php
/**
 * Pinoco web site environment
 * It makes existing static web site dynamic transparently.
 *
 * PHP Version 5
 *
 * @category Pinoco
 * @package  Pinoco
 * @author   Hisateru Tanaka <tanakahisateru@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  0.1.0-beta1
 * @link     
 */

/**
 * Flow control object
 */
class Pinoco_FlowControl extends Exception {
}

/**
 * Flow control object
 */
class Pinoco_FlowControlSkip extends Pinoco_FlowControl {
}

/**
 * Flow control object
 */
class Pinoco_FlowControlTerminate extends Pinoco_FlowControl {
}

/**
 * Flow control object
 */
class Pinoco_FlowControlHttpError extends Pinoco_FlowControl {
    public function __construct($code, $title, $message)
    {
        $this->code = $code;
        $this->title = $title;
        $this->message = $message;
    }
    public function respond($pinoco)
    {
        $pref = $pinoco->sysdir . "/error/";
        foreach(array($this->code . '.php', 'default.php') as $errfile) {
            if(file_exists($pref . $errfile)) {
                $pinoco->include_with_this($pref . $errfile, get_object_vars($this));
                return;
            }
        }
        header("HTTP/1.0 " . $this->code . " " . $this->title);
        header("Content-Type: text/html; charset=iso-8859-1");
        echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">' . "\n";
        echo "<html><head>\n";
        echo "<title>" . $this->code . " " . $this->title . "</title>\n";
        echo "</head><body>\n";
        echo "<h1>" . $this->code . " " . $this->title . "</h1>\n";
        echo "<p>" . $this->message . "</p>\n";
        echo "</body></html>";
    }
}

/**
 * Flow control object
 */
class Pinoco_FlowControlHttpRedirect extends Pinoco_FlowControlHttpError {
    public function __construct($url, $external=FALSE)
    {
        $this->url = $url;
        $this->external = $external;
    }
    public function respond($pinoco)
    {
        $protocol = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS']) ? "https" : "http";
        $server_prefix = $protocol . '://' . $_SERVER['SERVER_NAME'];
        $fixedurl = "";
        if(preg_match('/^\w+:\/\/[^\/]/', $this->url)) {
            $fixedurl = $this->url;
        }
        else if(preg_match('/^\/\/[^\/]/', $this->url)) {
            $fixedurl = $protocol . ':' . $this->url;
        }
        else if(preg_match('/^\/[^\/]?/', $this->url)) {
            if($this->extrenal) {
                $fixedurl = $server_prefix. $this->url;
            }
            else {
                $fixedurl = $server_prefix. $pinoco->url($this->url);
            }
        }
        else {
            $fixedurl = $server_prefix. $pinoco->url($this->url);
        }
        header('Location: ' . $fixedurl);
    }
}