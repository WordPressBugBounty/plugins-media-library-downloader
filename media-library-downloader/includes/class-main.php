<?php
if ( ! class_exists( 'MLD_Class' ) ) {
    class MLD_Class {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'current_screen', array( $this, 'mld_cleanup_old_temp_files' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'mld_enqueue_back' ) );
            add_action( 'wp_ajax_download_files', array( $this, 'mld_download_files' ) );
            add_action( 'wp_ajax_mld_serve_zip', array( $this, 'mld_serve_zip' ) );
            add_action( 'init', array( $this, 'schedule_cleanup' ) );
            add_filter( 'bulk_actions-upload', array( $this, 'mld_register_bulk_action' ) );
            add_filter( 'handle_bulk_actions-upload', array( $this, 'mld_handle_bulk_action' ), 10, 3 );
        }

        /**
         * Register bulk download action in list view.
         *
         * @param array $actions Bulk actions.
         * @return array
         */
        public function mld_register_bulk_action( $actions ) {
            $actions['mld-download-files'] = __( 'Download selected files', 'media-library-downloader' );
            return $actions;
        }

        /**
         * Fallback when bulk action form is submitted without JavaScript.
         *
         * @param string $redirect_to Redirect URL.
         * @param string $doaction    Bulk action slug.
         * @param array  $post_ids    Selected attachment IDs.
         * @return string
         */
        public function mld_handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
            if ( 'mld-download-files' !== $doaction ) {
                return $redirect_to;
            }

            if ( ! current_user_can( 'upload_files' ) ) {
                return add_query_arg( 'mld_download_error', 'unauthorized', $redirect_to );
            }

            $valid_ids = $this->mld_filter_accessible_attachments( array_map( 'absint', (array) $post_ids ) );
            if ( empty( $valid_ids ) ) {
                return add_query_arg( 'mld_download_error', 'no_files', $redirect_to );
            }

            $token = wp_generate_password( 32, false, false );
            set_transient( 'mld_pending_download_' . $token, $valid_ids, 5 * MINUTE_IN_SECONDS );

            return add_query_arg(
                array(
                    'mld_pending_download' => $token,
                    'mode'                 => 'list',
                ),
                $redirect_to
            );
        }

        /**
         * Remove expired temp files on media screen load.
         */
        public function mld_cleanup_old_temp_files() {
            $current_screen_obj = get_current_screen();
            if ( ! $current_screen_obj || $current_screen_obj->base !== 'upload' ) {
                return;
            }

            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                return;
            }

            $settings       = get_option( 'mld_settings', array() );
            $cleanup_hours  = isset( $settings['cleanup_interval'] ) ? (int) $settings['cleanup_interval'] : 24;
            $cutoff_time    = time() - ( $cleanup_hours * HOUR_IN_SECONDS );
            $files          = glob( MLD_TEMP_PATH . '*.zip' );

            if ( ! $files ) {
                return;
            }

            foreach ( $files as $file ) {
                if ( ! is_file( $file ) ) {
                    continue;
                }

                $real_file = realpath( $file );
                $real_temp = realpath( MLD_TEMP_PATH );

                if ( false === $real_file || false === $real_temp || strpos( $real_file, $real_temp ) !== 0 ) {
                    continue;
                }

                if ( filemtime( $file ) < $cutoff_time ) {
                    unlink( $file );
                }
            }
        }

        /**
         * Recursively remove directory
         *
         * @param string $dir Directory path.
         * @return bool
         */
        private function mld_remove_directory( $dir ) {
            if ( ! is_dir( $dir ) ) {
                return false;
            }

            $files = array_diff( scandir( $dir ), array( '.', '..' ) );
            foreach ( $files as $file ) {
                $path = $dir . '/' . $file;
                if ( is_dir( $path ) ) {
                    $this->mld_remove_directory( $path );
                } else {
                    unlink( $path );
                }
            }
            return rmdir( $dir );
        }

        /**
         * Enqueue scripts
         */
        public function mld_enqueue_back() {
            $current_screen = get_current_screen();
            if ( ! $current_screen || $current_screen->base !== 'upload' ) {
                return;
            }

            wp_enqueue_script( 'mld-admin-script', MLD_ASSETS_JS . 'admin.js', array( 'jquery' ), MLD_VERSION, true );

            $pending_ids = array();
            if ( isset( $_GET['mld_pending_download'] ) ) {
                $token = sanitize_text_field( wp_unslash( $_GET['mld_pending_download'] ) );
                $ids   = get_transient( 'mld_pending_download_' . $token );
                if ( is_array( $ids ) ) {
                    $pending_ids = array_values( array_map( 'absint', $ids ) );
                    delete_transient( 'mld_pending_download_' . $token );
                }
            }

            wp_localize_script(
                'mld-admin-script',
                'admin',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'mld_download_nonce' ),
                )
            );
            wp_localize_script(
                'mld-admin-script',
                'mld_pending',
                array(
                    'ids' => $pending_ids,
                )
            );
            wp_localize_script(
                'mld-admin-script',
                'mld_i18n',
                array(
                    'download_files'   => __( 'Download selected files', 'media-library-downloader' ),
                    'download_single'  => __( 'Download', 'media-library-downloader' ),
                    'no_files_selected'=> __( 'No files selected', 'media-library-downloader' ),
                    'download_error'   => __( 'An error occurred while downloading files', 'media-library-downloader' ),
                    'preparing_download'=> __( 'Preparing download...', 'media-library-downloader' ),
                    'invalid_file'     => __( 'Invalid file selected', 'media-library-downloader' ),
                    'unauthorized'     => __( 'Unauthorized access', 'media-library-downloader' ),
                    'list_view_hint'   => __( 'Tip: Select files with the checkboxes, then click the blue "Download selected files" button or use Bulk actions > Download selected files > Apply.', 'media-library-downloader' ),
                    'dismiss_hint'     => __( 'Dismiss', 'media-library-downloader' ),
                )
            );
        }

        /**
         * Download files
         */
        public function mld_download_files() {
            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'mld_download_nonce' ) ) {
                wp_send_json_error( __( 'Security check failed', 'media-library-downloader' ), 403 );
            }

            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( __( 'Unauthorized access', 'media-library-downloader' ), 403 );
            }

            $attachment_ids = $this->mld_validate_attachment_ids();
            if ( empty( $attachment_ids ) ) {
                wp_send_json_error( __( 'No valid files selected', 'media-library-downloader' ), 400 );
            }

            $valid_ids = $this->mld_filter_accessible_attachments( $attachment_ids );
            if ( empty( $valid_ids ) ) {
                wp_send_json_error( __( 'No accessible files found', 'media-library-downloader' ), 403 );
            }

            if ( count( $valid_ids ) === 1 ) {
                $this->mld_download_single_file( $valid_ids[0] );
                return;
            }

            $this->mld_download_multiple_files( $valid_ids );
        }

        /**
         * Serve ZIP file through authenticated AJAX endpoint.
         */
        public function mld_serve_zip() {
            $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

            if ( empty( $token ) || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'mld_zip_' . $token ) ) {
                wp_die( esc_html__( 'Security check failed', 'media-library-downloader' ), 403 );
            }

            if ( ! current_user_can( 'upload_files' ) ) {
                wp_die( esc_html__( 'Unauthorized access', 'media-library-downloader' ), 403 );
            }

            $zip_path = get_transient( 'mld_zip_' . $token );
            if ( ! $zip_path || ! file_exists( $zip_path ) ) {
                wp_die( esc_html__( 'File not found', 'media-library-downloader' ), 404 );
            }

            $real_file = realpath( $zip_path );
            $real_temp = realpath( MLD_TEMP_PATH );
            if ( false === $real_file || false === $real_temp || strpos( $real_file, $real_temp ) !== 0 ) {
                wp_die( esc_html__( 'Invalid file path', 'media-library-downloader' ), 403 );
            }

            $filename = basename( $zip_path );
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $zip_path ) );
            readfile( $zip_path );

            unlink( $zip_path );
            delete_transient( 'mld_zip_' . $token );
            exit;
        }

        /**
         * Validate and sanitize attachment IDs
         *
         * @return array
         */
        private function mld_validate_attachment_ids() {
            if ( ! isset( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
                return array();
            }

            $attachment_ids = array();
            foreach ( $_POST['ids'] as $id ) {
                $clean_id = absint( $id );
                if ( $clean_id > 0 && get_post_type( $clean_id ) === 'attachment' ) {
                    $attachment_ids[] = $clean_id;
                }
            }

            return array_unique( $attachment_ids );
        }

        /**
         * Filter attachments that user can access
         *
         * @param array $attachment_ids Attachment IDs.
         * @return array
         */
        private function mld_filter_accessible_attachments( $attachment_ids ) {
            $valid_ids = array();

            foreach ( $attachment_ids as $attachment_id ) {
                if ( current_user_can( 'edit_post', $attachment_id ) ) {
                    $file_path = get_attached_file( $attachment_id );
                    if ( $file_path && file_exists( $file_path ) ) {
                        $valid_ids[] = $attachment_id;
                    }
                }
            }

            return $valid_ids;
        }

        /**
         * Download single file
         *
         * @param int $attachment_id Attachment ID.
         */
        private function mld_download_single_file( $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );
            $file_name = basename( $file_path );

            if ( ! $file_path || ! file_exists( $file_path ) ) {
                wp_send_json_error( __( 'File not found', 'media-library-downloader' ), 404 );
            }

            $this->log_download( array( $attachment_id ), 'single' );

            $file_url = wp_get_attachment_url( $attachment_id );
            wp_send_json_success(
                array(
                    'url'      => $file_url,
                    'filename' => $file_name,
                    'single'   => true,
                )
            );
        }

        /**
         * Download multiple files as ZIP
         *
         * @param array $attachment_ids Attachment IDs.
         */
        private function mld_download_multiple_files( $attachment_ids ) {
            if ( ! class_exists( 'ZipArchive' ) ) {
                wp_send_json_error( __( 'ZIP functionality not available', 'media-library-downloader' ), 500 );
            }

            if ( ! is_dir( MLD_TEMP_PATH ) && ! wp_mkdir_p( MLD_TEMP_PATH ) ) {
                wp_send_json_error( __( 'Cannot create temporary directory', 'media-library-downloader' ), 500 );
            }

            $timestamp   = time();
            $folder_name = $this->generate_zip_filename( $timestamp );
            $zip_path    = MLD_TEMP_PATH . $folder_name . '.zip';

            $zip = new ZipArchive();
            if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
                wp_send_json_error( __( 'Cannot create ZIP file', 'media-library-downloader' ), 500 );
            }

            $file_count = 0;
            $total_size = 0;
            $max_size   = $this->mld_get_max_download_size();
            $size_limit_hit = false;

            foreach ( $attachment_ids as $attachment_id ) {
                $file_path = get_attached_file( $attachment_id );
                $file_name = basename( $file_path );

                if ( ! $file_path || ! file_exists( $file_path ) ) {
                    continue;
                }

                $file_size = filesize( $file_path );
                if ( $total_size + $file_size > $max_size ) {
                    $size_limit_hit = true;
                    break;
                }

                $unique_name = $this->mld_get_unique_filename( $zip, $file_name );

                if ( $zip->addFile( $file_path, $unique_name ) ) {
                    $file_count++;
                    $total_size += $file_size;
                }
            }

            $zip->close();

            if ( $file_count === 0 ) {
                if ( file_exists( $zip_path ) ) {
                    unlink( $zip_path );
                }
                wp_send_json_error( __( 'No files could be added to ZIP', 'media-library-downloader' ), 500 );
            }

            $this->log_download( $attachment_ids, 'zip' );

            $token = wp_generate_password( 32, false, false );
            set_transient( 'mld_zip_' . $token, $zip_path, HOUR_IN_SECONDS );

            $download_url = add_query_arg(
                array(
                    'action' => 'mld_serve_zip',
                    'token'  => $token,
                    'nonce'  => wp_create_nonce( 'mld_zip_' . $token ),
                ),
                admin_url( 'admin-ajax.php' )
            );

            wp_send_json_success(
                array(
                    'url'            => $download_url,
                    'filename'       => $folder_name . '.zip',
                    'file_count'     => $file_count,
                    'single'         => false,
                    'size_limit_hit' => $size_limit_hit,
                )
            );
        }

        /**
         * Get maximum download size in bytes
         *
         * @return int
         */
        private function mld_get_max_download_size() {
            $settings    = get_option( 'mld_settings', array() );
            $max_size_mb = $settings['max_download_size'] ?? 100;
            $max_size    = $max_size_mb * 1024 * 1024;

            $max_size = apply_filters( 'mld_max_download_size', $max_size );
            return min( $max_size, wp_max_upload_size() * 2 );
        }

        /**
         * Get unique filename for ZIP to handle duplicates
         *
         * @param ZipArchive $zip Zip archive.
         * @param string       $filename Filename.
         * @return string
         */
        private function mld_get_unique_filename( $zip, $filename ) {
            $name    = pathinfo( $filename, PATHINFO_FILENAME );
            $ext     = pathinfo( $filename, PATHINFO_EXTENSION );
            $counter = 1;
            $unique_name = $filename;

            while ( $zip->locateName( $unique_name ) !== false ) {
                $unique_name = $name . '_' . $counter . ( $ext ? '.' . $ext : '' );
                $counter++;
            }

            return $unique_name;
        }

        /**
         * Generate ZIP filename based on pattern
         *
         * @param int $timestamp Timestamp.
         * @return string
         */
        private function generate_zip_filename( $timestamp ) {
            $settings = get_option( 'mld_settings', array() );
            $pattern  = $settings['zip_filename_pattern'] ?? 'media-library-download-{timestamp}';

            $current_user = wp_get_current_user();
            $replacements = array(
                '{timestamp}' => $timestamp,
                '{date}'      => date( 'Y-m-d', $timestamp ),
                '{user}'      => $current_user->user_login,
                '{userid}'    => $current_user->ID,
            );

            $filename = str_replace( array_keys( $replacements ), array_values( $replacements ), $pattern );
            return sanitize_file_name( $filename );
        }

        /**
         * Log download activity
         *
         * @param array  $attachment_ids Attachment IDs.
         * @param string $type Download type.
         */
        private function log_download( $attachment_ids, $type ) {
            $settings = get_option( 'mld_settings', array() );
            if ( empty( $settings['enable_logging'] ) ) {
                return;
            }

            $current_user = wp_get_current_user();
            $log_entry    = array(
                'timestamp'      => time(),
                'user'           => $current_user->user_login,
                'user_id'        => $current_user->ID,
                'file_count'     => count( $attachment_ids ),
                'attachment_ids' => $attachment_ids,
                'type'           => $type,
                'ip'             => $this->get_user_ip(),
            );

            $logs   = get_option( 'mld_download_logs', array() );
            $logs[] = $log_entry;

            if ( count( $logs ) > 1000 ) {
                $logs = array_slice( $logs, -1000 );
            }

            update_option( 'mld_download_logs', $logs );
            do_action( 'mld_download_logged', $log_entry );
        }

        /**
         * Get user IP address
         *
         * @return string
         */
        private function get_user_ip() {
            if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            }
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            }
            if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            }
            return 'unknown';
        }

        /**
         * Schedule automatic cleanup
         */
        public function schedule_cleanup() {
            if ( ! wp_next_scheduled( 'mld_cleanup_temp_files' ) ) {
                wp_schedule_event( time(), 'hourly', 'mld_cleanup_temp_files' );
            }

            add_action( 'mld_cleanup_temp_files', array( $this, 'automatic_cleanup' ) );
        }

        /**
         * Automatic cleanup via cron
         */
        public function automatic_cleanup() {
            $settings         = get_option( 'mld_settings', array() );
            $cleanup_interval = $settings['cleanup_interval'] ?? 24;
            $cutoff_time      = time() - ( $cleanup_interval * HOUR_IN_SECONDS );

            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                return;
            }

            $files = glob( MLD_TEMP_PATH . '*.zip' );
            if ( ! $files ) {
                return;
            }

            foreach ( $files as $file ) {
                if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
                    unlink( $file );
                }
            }

            if ( ! empty( $settings['enable_logging'] ) ) {
                $log_entry = array(
                    'timestamp'      => time(),
                    'user'           => 'system',
                    'user_id'        => 0,
                    'file_count'     => 0,
                    'attachment_ids' => array(),
                    'type'           => 'cleanup',
                    'ip'             => 'system',
                );

                $logs   = get_option( 'mld_download_logs', array() );
                $logs[] = $log_entry;
                update_option( 'mld_download_logs', $logs );
            }
        }
    }
}

new MLD_Class();
