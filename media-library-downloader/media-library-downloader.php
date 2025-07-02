<?php
/**
 * Plugin Name:       Media Library Downloader
 * Plugin URI:        https://wordpress.org/plugins/media-library-downloader/
 * Description:       Download multiple media library files in one click !
 * Version:           1.3.3
 * Tags:              library, media, files, download, downloader, WordPress
 * Requires at least: 5.0 or higher
 * Requires PHP:      5.6
 * Tested up to:      6.6.1
 * Stable tag:        1.3.3
 * Author:            M . Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Contributors:      M . Code
 * Donate link:       https://ko-fi.com/devloper
 */

/**
 * Exit
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( !class_exists( 'MediaLibraryDownloader' ) ) {
    /**
     * MediaLibraryDownloader
     */
    class MediaLibraryDownloader {

        /**
         * Constructor
         */
        public function __construct() {
            define( 'MLD_PATH', plugin_dir_path( __FILE__ ) );
            define( 'MLD_BASENAME', plugin_basename( __FILE__ ) );
            define( 'MLD_TEMP_PATH', plugin_dir_path( __FILE__ ) . 'temp/' );
            define( 'MLD_TEMP_URL', plugin_dir_url( __FILE__ ) . 'temp/' );
            define( 'MLD_ASSETS_JS', plugin_dir_url(__FILE__ ) . '/assets/js/' );
            define( 'MLD_INCLUDES', plugin_dir_path(__FILE__ ) . 'includes/' );
            $this->setup_actions();
            $this->include_files();
        }

        /**
         * Setting up Hooks
         */
        public function setup_actions() {
            register_activation_hook( __FILE__, array( $this, 'mld_check_requirements' ) );
        }

        /**
         * Check Requirements
         */
        public function mld_check_requirements() {
            $requirements = array(
                array(
                    'type' => 'module',
                    'link' => 'https://www.php.net/manual/fr/zip.installation.php',
                    'name' => 'zip',
                ),
                array(
                    'type' => 'module',
                    'link' => 'https://www.php.net/manual/fr/curl.installation.php',
                    'name' => 'curl',
                ),
                array(
                    'type' => 'value',
                    'name' => 'allow_url_fopen',
                ),
            );

            if ( $requirements ) :
                $loaded_php_extensions = get_loaded_extensions();
                if ( $loaded_php_extensions ) :
                    foreach ( $requirements as $requirement ) :
                        $requirement_type = $requirement['type'];
                        $requirement_url  = $requirement['link'];
                        $requirement_name = $requirement['name'];
                        switch ( $requirement_type ) :
                            case 'module':
                                if ( !in_array( $requirement_name, $loaded_php_extensions, true ) ) :
                                    echo '<div class="notice notice-error is-dismissible"><p>';
                                    printf( "Le module PHP <a href=' . $requirement_url . '><strong>$requirement_name</strong></a> n'est pas installé. <br> L'extension ne fonctionnera pas correctement. <br> Veuillez l'installer et réactiver le plugin." );
                                    echo '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Ignorer cette notification.</span></button></div>';
                                endif;
                                break;
                            case 'value':
                                if ( !ini_get( $requirement_name ) ) :
                                    echo '<div class="notice notice-error is-dismissible"><p>';
                                    printf( '<strong>' . $requirement_name . '</strong> is not enable in the php.ini file, activate it and retry.' );
                                    echo '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Ignorer cette notification.</span></button></div>';
                                endif;
                                break;
                        endswitch;
                    endforeach;
                endif;
            endif;
        }

        /**
         * Include files
         */
        public function include_files() {
            require MLD_INCLUDES . 'class-main.php';
        }
    }
    new MediaLibraryDownloader();
}
