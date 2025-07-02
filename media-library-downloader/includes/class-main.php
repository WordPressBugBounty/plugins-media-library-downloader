<?php
if ( !class_exists( 'MLD_Class' ) ) {
    class MLD_Class {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'current_screen', array( $this, 'mld_empty_temp_folder' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'mld_enqueue_back' ) );
            add_action( 'wp_ajax_download_files', array( $this, 'mld_download_files' ) );
        }

        /**
         * Empty temp folder
         */
        public function mld_empty_temp_folder() {
            $current_screen_obj = get_current_screen();
            if ( !$current_screen_obj && $current_screen_obj->base !== 'upload' ) :
                return;
            endif;
            $files = glob( MLD_TEMP_PATH . '/*' );
            foreach ( $files as $file ) :
                if ( is_file( $file ) ) :
                    unlink( $file );
                endif;
                if ( is_dir( $file ) ) :
                    rmdir( $file );
                endif;
            endforeach;
        }

        /**
         * Enqueue scripts
         */
        public function mld_enqueue_back() {
            wp_enqueue_script( 'mld-admin-script', MLD_ASSETS_JS . 'admin.js', array( 'jquery' ), 1.0, true );
            wp_localize_script( 'mld-admin-script', 'admin', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
            wp_localize_script( 'mld-admin-script', 'mld_i18n', array(
                'download_files' => __('Download selected files', 'media-library-downloader'),
                'no_files_selected' => __('No files selected', 'media-library-downloader'),
                'download_error' => __('An error occurred while downloading files', 'media-library-downloader')
            ));
        }

        /**
         * Download files
         */
        public function mld_download_files() {

            // Prevent non authorized user to make action
            if ( !current_user_can('upload_files') ) :
                wp_send_json_error('Unauthorized access', 403);
                wp_die();
            endif;

            // Check $_POST data
            if ( isset( $_POST['ids'] ) && !empty( $_POST['ids'] ) ) :
                $attachment_ids = filter_input( INPUT_POST, 'ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
            endif;

            /**
             * Create temp folder
             */
            $timestamp     = gmdate( 'U' );
            $folder_name   = 'media-library-downloader-' . $timestamp;
            $folder_path   = MLD_TEMP_PATH . $folder_name;
            $root          = MLD_TEMP_URL;
            $create_folder = mkdir( $folder_path );

            if ( $create_folder && $attachment_ids ) :
                $zip = new ZipArchive();
                if ( $zip->open( $folder_path . '.zip', ZipArchive::CREATE ) ) :
                    foreach ( $attachment_ids as $attachment_id ) :
                        $attachment_name = basename( get_attached_file( $attachment_id ) );
                        $attachment_url  = get_attached_file($attachment_id );
                        if ( ini_get( 'allow_url_fopen' ) ) {
                            $file_content = file_get_contents( $attachment_url ); //phpcs:disable
                        } else {
                            $ch = curl_init();
                            curl_setopt( $ch, CURLOPT_URL, $attachment_url );
                            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                            $file_content = curl_exec( $ch );
                            curl_close( $ch );
                        }
                        $zip->addFromString( $attachment_name, $file_content );
                    endforeach;
                    $zip->close();
                    header( 'Content-disposition: attachment; filename=' . $folder_name . '.zip' );
                    header( 'Content-type: application/zip' );
                    wp_send_json_success( $root . $folder_name . '.zip', null, 0 );
                    wp_die();
                endif;
            endif;
        }
    }
}

new MLD_Class();
