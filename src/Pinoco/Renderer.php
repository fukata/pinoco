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
 * Abstract HTML page renderer
 */
abstract class Pinoco_Renderer {
    protected $_sysref;
    protected $_cfg;
    public function __construct(&$sys)
    {
        $this->_sysref = &$sys;
        $this->_cfg = new Pinoco_Vars();
    }
    
    public function __toString() { return __CLASS__; }
    
    public function __get($name)
    {
        if($name == 'cfg'){ return $this->_cfg; }
        return NULL;
    }
    
    abstract public function render($page, $extravars=array());
}

/**
 * Preferred HTML page renderer using PHPTAL
 */
class Pinoco_TALRenderer extends Pinoco_Renderer {
    public function render($page, $extravars=array())
    {
        $this->_sysref->using('PHPTAL.php');
        $template = new PHPTAL($page);
        
        //config
        $template->setTemplateRepository($this->_sysref->basedir);
        foreach($this->cfg as $k => $v) {
            $meth = 'set' . strtoupper($k[0]) . substr($k, 1);
            if(method_exists($template, $meth)) {
                call_user_func(array($template, $meth), $v);
            }
        }
        
        //extract vars
        foreach($this->_sysref->autolocal as $name=>$value) {
            $template->set($name, $value);
        }
        foreach($extravars as $name=>$value) {
            $template->set($name, $value);
        }
        $template->set('this', $this->_sysref);
        
        //exec
        //ob_start();
        try {
            echo $template->execute();
        }
        catch (Exception $e) {
            //$dump = ob_get_clean();
            if(!$e->getFile()) {
                $msg = $e->getMessage();
            }
            if(!$e->getLine()) {
                $msg = sprintf("%s(%s)", $e->getMessage(), $e->getFile());
            }
            else {
                $msg = sprintf("%s(%s:%d)", $e->getMessage(), $e->getFile(), $e->getLine());
            }
            trigger_error($msg, E_USER_WARNING);
            //throw $e;
            //return;
        }
        //ob_end_flush();
    }
}

function phptal_tales_url($src, $nothrow)
{
    $src = trim($src);
    $src = preg_match('/^[A-Za-z0-9_]+:/', $src) ? phptal_tales($src, $nothrow) : "'" . $src . "'";
    return '$ctx->this->url(' . $src . ')';
}

/**
 * Default HTML page renderer using native PHP.
 */
class Pinoco_NativeRenderer extends Pinoco_Renderer {
    public function render($page, $extravars=array())
    {
        $vars = $this->_sysref->autolocal->to_array();
        foreach($extravars as $k=>$v) {
            $vars[$k] = $v;
        }
        $this->_sysref->include_with_this($this->_sysref->basedir . "/" . $page, $vars);
    }
}

/**
 * Null renderer.
 * @package Pinoco
 */
class Pinoco_NullRenderer extends Pinoco_Renderer {
    public function render($page, $extravars=array())
    {
    }
}

/**
 * Plain text output (experimental)
 * @package Pinoco
 */
class Pinoco_PlainTextRenderer extends Pinoco_Renderer {
    public function render($page, $extravars=array())
    {
        echo $page;
    }
}
