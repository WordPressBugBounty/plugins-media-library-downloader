<?php
/**
 * Plugin Name:       Media Library Downloader
 * Plugin URI:        https://wordpress.org/plugins/media-library-downloader/
 * Description:       Download multiple media library files in one click !
 * Version:           1.4.0
 * Tags:              library, media, files, download, downloader, WordPress
 * Requires at least: 5.0 or higher
 * Requires PHP:      5.6
 * Tested up to:      6.8.2
 * Stable tag:        1.4.0
 * Author:            Michael Revellin-Clerc
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Contributors:      Michael Revellin-Clerc
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
            $this->define_constants();
            $this->setup_actions();
            $this->include_files();
        }

        /**
         * Define plugin constants
         */
        private function define_constants() {
            define( 'MLD_VERSION', '1.4.0' );
            define( 'MLD_PATH', plugin_dir_path( __FILE__ ) );
            define( 'MLD_URL', plugin_dir_url( __FILE__ ) );
            define( 'MLD_BASENAME', plugin_basename( __FILE__ ) );
            define( 'MLD_TEMP_PATH', plugin_dir_path( __FILE__ ) . 'temp/' );
            define( 'MLD_TEMP_URL', plugin_dir_url( __FILE__ ) . 'temp/' );
            define( 'MLD_ASSETS_JS', plugin_dir_url( __FILE__ ) . 'assets/js/' );
            define( 'MLD_INCLUDES', plugin_dir_path( __FILE__ ) . 'includes/' );
        }

        /**
         * Setting up Hooks
         */
        public function setup_actions() {
            register_activation_hook( __FILE__, array( $this, 'mld_check_requirements' ) );
            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
            add_action( 'init', array( $this, 'mld_init' ) );
        }

        /**
         * Load plugin textdomain
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'media-library-downloader', false, dirname( MLD_BASENAME ) . '/languages' );
        }

        /**
         * Initialize plugin
         */
        public function mld_init() {
            // Create temp directory if it doesn't exist
            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                wp_mkdir_p( MLD_TEMP_PATH );
            }
        }

        /**
         * Check Requirements
         */
        public function mld_check_requirements() {
            $requirements = array(
                array(
                    'type' => 'module',
                    'link' => 'https://www.php.net/manual/en/zip.installation.php',
                    'name' => 'zip',
                    'required' => true,
                ),
                array(
                    'type' => 'module', 
                    'link' => 'https://www.php.net/manual/en/curl.installation.php',
                    'name' => 'curl',
                    'required' => false, // Not strictly required, fallback exists
                ),
            );

            $errors = array();
            $loaded_php_extensions = get_loaded_extensions();

            foreach ( $requirements as $requirement ) {
                $requirement_type = $requirement['type'];
                $requirement_url = $requirement['link'];
                $requirement_name = $requirement['name'];
                $is_required = $requirement['required'];

                switch ( $requirement_type ) {
                    case 'module':
                        if ( ! in_array( $requirement_name, $loaded_php_extensions, true ) ) {
                            if ( $is_required ) {
                                $errors[] = sprintf(
                                    /* translators: %1$s: requirement URL, %2$s: requirement name */
                                    __( 'The PHP module <a href="%1$s"><strong>%2$s</strong></a> is not installed. The plugin will not work correctly. Please install it and reactivate the plugin.', 'media-library-downloader' ),
                                    esc_url( $requirement_url ),
                                    esc_html( $requirement_name )
                                );
                            }
                        }
                        break;
                    
                    case 'value':
                        if ( ! ini_get( $requirement_name ) ) {
                            $errors[] = sprintf(
                                /* translators: %s: PHP setting name */
                                __( '<strong>%s</strong> is not enabled in the php.ini file. Please enable it and try again.', 'media-library-downloader' ),
                                esc_html( $requirement_name )
                            );
                        }
                        break;
                }
            }

            // Display errors if any
            if ( ! empty( $errors ) ) {
                add_action( 'admin_notices', function() use ( $errors ) {
                    foreach ( $errors as $error ) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( $error ) . '</p></div>';
                    }
                });
            }

            return empty( $errors );
        }

        /**
         * Include files
         */
        public function include_files() {
            require MLD_INCLUDES . 'class-main.php';
            
            // Include admin class only in admin area
            if ( is_admin() ) {
                require MLD_INCLUDES . 'class-admin.php';
            }
        }
    }
    new MediaLibraryDownloader();
}
