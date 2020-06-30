<?php
/**
 * DokuWiki Plugin lightweightcss (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net /// software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_lightweightcss extends DokuWiki_Action_Plugin {

    private const DEFAULT_ADMIN_INCLUDE = array(
        '/css/admin/',
        '/lib/plugins/config/',
        '/lib/plugins/searchindex/',
        '/lib/plugins/sync/',
        '/lib/plugins/batchedit/',
        '/lib/plugins/usermanager/',
        '/lib/plugins/upgrade/',
        '/lib/plugins/extension/',
        '/lib/plugins/tagsections/',
        '/lib/plugins/move/',
        '/lib/plugins/acl/',
        '/lib/plugins/multiorphan/',
        '/lib/plugins/edittable/',
        '/lib/plugins/sectionedit/',
        '/lib/plugins/wrap/',
        '/lib/scripts/',
        '/lib/styles/',
    );

    private const DEFAULT_INCLUDE = array(
        '/lib/tpl/',
        '/lib/plugins/',
    );

    private const DEFAULT_EXCLUDE = array(
        '/css/admin/',
        '/lib/plugins/dw2pdf/',
        '/lib/plugins/box2/',
        '/lib/plugins/config/',
        '/lib/plugins/uparrow/',
        '/lib/plugins/imagebox/',
        '/lib/plugins/imagereference/',

        '/lib/plugins/searchindex/',
        '/lib/plugins/poll/',
        '/lib/plugins/cloud/',
        '/lib/plugins/sync/',
        '/lib/plugins/wrap/',
        '/lib/plugins/blog/',
        '/lib/plugins/batchedit/',
        '/lib/plugins/usermanager/',
        '/lib/plugins/upgrade/',
        '/lib/plugins/imagebox/',
        '/lib/plugins/extension/',
        '/lib/plugins/tagsections/',
        '/lib/plugins/javadoc/',
        '/lib/plugins/toctweak/',
        '/lib/plugins/move/',
        '/lib/plugins/acl/',
        '/lib/plugins/multiorphan/',
        '/lib/plugins/edittable/',
        '/lib/plugins/sectionedit/',
        '/lib/plugins/styling/',
        '/lib/plugins/tag/',
/*
        'lib/scripts/',
        'lib/styles/',
        'conf/userall',
*/    
    );

    private const templateStyles = null;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        global $INPUT;
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_tpl_metaheader_output');
        $controller->register_hook('CSS_STYLES_INCLUDED', 'BEFORE', $this, 'handle_css_styles');

        $controller->register_hook('CSS_CACHE_USE', 'BEFORE', $this, 'handle_use_cache');
    }

    /**
     * Insert an extra script tag for users that have AUTH_EDIT or better
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_tpl_metaheader_output(Doku_Event &$event, $param) {
        global $ID, $updateVersion, $conf;

        // add css if user has better auth than AUTH_EDIT
        if ( auth_quickaclcheck( $ID ) >= AUTH_EDIT ) {
            $tseed = $updateVersion;
            $tseed = md5($tseed.'admin');
            $event->data['link'][] = array(
                'rel' => 'stylesheet', 'type'=> 'text/css',
                'href'=> DOKU_BASE.'lib/exe/css.php?t='.rawurlencode($conf['template']).'&f=admin&tseed='.$tseed
            );
        }
    }
    
    /**
     * This function serves debugging purposes and has to be enabled in the register phase
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_use_cache(Doku_Event &$event, $param) {
        global $INPUT;

        // We need different keys for each style sheet.
        $event->data->key .= $INPUT->str('f', 'style');
        $event->data->cache = getCacheName( $event->data->key, $event->data->ext );

        return true;
    }

    /**
     * Finally, handle the JS script list. The script would be fit to do even more stuff / types
     * but handles only admin and default currently.
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_css_styles(Doku_Event &$event, $param) {
        global $INPUT;
        
        $this->setupStyles();

        switch( $event->data['mediatype'] ) {
            
            case 'print':
            case 'screen':
            case 'all':
                // Filter for user styles
                $allowed = array_filter( array_keys($event->data['files']), array($this, 'filter_css') );
                $event->data['files'] = array_intersect_key($event->data['files'], array_flip($allowed));
                //$event->data['encapsulate'] = $INPUT->str('f', 'style') != 'admin';
                break;
            
            case 'speech':
            case 'DW_DEFAULT':
                $event->preventDefault();
                break;
        }
    }
    
    /**
     * A simple filter function to check the input string against a list of path-parts that are allowed
     *
     * @param string    $str   the script file to check against the list
     * @param mixed     $list  the list of path parts to test
     * @return boolean
     */
    private function includeFilter( $str, $list ) {
        foreach( $list as $entry ) {
            if ( strpos( $str, $entry ) ) return true;
        }
        
        return false;
    }
    
    /**
     * A simple filter function to check the input string against a list of path-parts that are allowed
     * Is the inversion of includeFilter( $str, $list )
     *
     * @param string    $str   the script file to check against the list
     * @param mixed     $list  the list of path parts to test
     * @return boolean
     */
    private function excludeFilter( $str, $list ) {
        return !$this->includeFilter( $str, $list );
    }
  
    /**
     * Filters scripts that are intended for admins only
     *
     * @param string    $script   the script file to check against the list
     * @return boolean
     */
    private function filter_css( $script ) {
        global $INPUT;
        if ( $INPUT->str('f', 'style') == 'admin' ) {
            return $this->includeFilter( $script, $this->templateStyles['admin'] );
        } else {
            return $this->includeFilter( $script, $this->templateStyles['include']) && $this->excludeFilter( $script, $this->templateStyles['exclude']);
        }
    }

    private function setupStyles() {
        global $CONF;
        global $INPUT;

        if ( !is_null( $this->templateStyles) ) {
            return;
        }

        $this->templateStyles = array(
            'admin' => action_plugin_lightweightcss::DEFAULT_ADMIN_INCLUDE,
            'include' => action_plugin_lightweightcss::DEFAULT_INCLUDE,
            'exclude' => action_plugin_lightweightcss::DEFAULT_EXCLUDE
        );

        $tpl = $INPUT->str('t', $conf['template']);
        $inifile = DOKU_INC . 'lib/tpl/' . $tpl . '/style.ini';

        if (!file_exists($inifile)) {
            return;
        }

        $styleini = parse_ini_file($inifile, true);
        if ( array_key_exists('lightweightcss', $styleini) ) {

            foreach( $styleini['lightweightcss'] as $file => $mode ) {

                switch( $mode ) {
                    case 'admin':
                    case 'include':
                    case 'exclude':
                        break;
                    default:
                        continue 2;
                }

                if ( !array_key_exists($mode, $this->templateStyles) ) {
                    $this->templateStyles[$mode] = array();
                }

                array_push( $this->templateStyles[$mode], $file);
                $this->templateStyles[$mode] = array_unique( $this->templateStyles[$mode] );
            }
        }
    }
}

// vim:ts=4:sw=4:et:
