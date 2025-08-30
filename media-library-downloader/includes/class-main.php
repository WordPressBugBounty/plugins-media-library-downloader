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
            add_action( 'init', array( $this, 'schedule_cleanup' ) );
        }

        /**
         * Empty temp folder
         */
        public function mld_empty_temp_folder() {
            $current_screen_obj = get_current_screen();
            if ( ! $current_screen_obj || $current_screen_obj->base !== 'upload' ) {
                return;
            }
            
            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                return;
            }
            
            $files = glob( MLD_TEMP_PATH . '/*' );
            if ( ! $files ) {
                return;
            }
            
            foreach ( $files as $file ) {
                // Security check: ensure file is within temp directory
                if ( strpos( realpath( $file ), realpath( MLD_TEMP_PATH ) ) !== 0 ) {
                    continue;
                }
                
                if ( is_file( $file ) ) {
                    unlink( $file );
                } elseif ( is_dir( $file ) ) {
                    $this->mld_remove_directory( $file );
                }
            }
        }
        
        /**
         * Recursively remove directory
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
            
            wp_enqueue_script( 'mld-admin-script', MLD_ASSETS_JS . 'admin.js', array( 'jquery' ), '1.3.4', true );
            wp_localize_script( 'mld-admin-script', 'admin', array( 
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'mld_download_nonce' )
            ) );
            wp_localize_script( 'mld-admin-script', 'mld_i18n', array(
                'download_files' => __( 'Download selected files', 'media-library-downloader' ),
                'download_single' => __( 'Download', 'media-library-downloader' ),
                'no_files_selected' => __( 'No files selected', 'media-library-downloader' ),
                'download_error' => __( 'An error occurred while downloading files', 'media-library-downloader' ),
                'preparing_download' => __( 'Preparing download...', 'media-library-downloader' ),
                'invalid_file' => __( 'Invalid file selected', 'media-library-downloader' ),
                'unauthorized' => __( 'Unauthorized access', 'media-library-downloader' )
            ));
        }

        /**
         * Download files
         */
        public function mld_download_files() {
            // Verify nonce for security
            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'mld_download_nonce' ) ) {
                wp_send_json_error( __( 'Security check failed', 'media-library-downloader' ), 403 );
            }

            // Check user permissions
            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( __( 'Unauthorized access', 'media-library-downloader' ), 403 );
            }

            // Validate and sanitize input
            $attachment_ids = $this->mld_validate_attachment_ids();
            if ( empty( $attachment_ids ) ) {
                wp_send_json_error( __( 'No valid files selected', 'media-library-downloader' ), 400 );
            }

            // Check if user can access these attachments
            $valid_ids = $this->mld_filter_accessible_attachments( $attachment_ids );
            if ( empty( $valid_ids ) ) {
                wp_send_json_error( __( 'No accessible files found', 'media-library-downloader' ), 403 );
            }

            // Handle single file download
            if ( count( $valid_ids ) === 1 ) {
                $this->mld_download_single_file( $valid_ids[0] );
                return;
            }

            // Handle multiple files download
            $this->mld_download_multiple_files( $valid_ids );
        }

        /**
         * Validate and sanitize attachment IDs
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
         */
        private function mld_filter_accessible_attachments( $attachment_ids ) {
            $valid_ids = array();
            
            foreach ( $attachment_ids as $attachment_id ) {
                // Check if attachment exists and user can edit it
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
         */
        private function mld_download_single_file( $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );
            $file_name = basename( $file_path );
            
            if ( ! $file_path || ! file_exists( $file_path ) ) {
                wp_send_json_error( __( 'File not found', 'media-library-downloader' ), 404 );
            }

            // Log the download
            $this->log_download( array( $attachment_id ), 'single' );

            // For single files, return the direct URL
            $file_url = wp_get_attachment_url( $attachment_id );
            wp_send_json_success( array(
                'url' => $file_url,
                'filename' => $file_name,
                'single' => true
            ) );
        }

        /**
         * Download multiple files as ZIP
         */
        private function mld_download_multiple_files( $attachment_ids ) {
            if ( ! class_exists( 'ZipArchive' ) ) {
                wp_send_json_error( __( 'ZIP functionality not available', 'media-library-downloader' ), 500 );
            }

            // Create temp directory if it doesn't exist
            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                if ( ! wp_mkdir_p( MLD_TEMP_PATH ) ) {
                    wp_send_json_error( __( 'Cannot create temporary directory', 'media-library-downloader' ), 500 );
                }
            }

            $timestamp = time();
            $folder_name = $this->generate_zip_filename( $timestamp );
            $zip_path = MLD_TEMP_PATH . $folder_name . '.zip';
            $zip_url = MLD_TEMP_URL . $folder_name . '.zip';

            $zip = new ZipArchive();
            if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== TRUE ) {
                wp_send_json_error( __( 'Cannot create ZIP file', 'media-library-downloader' ), 500 );
            }

            $file_count = 0;
            $total_size = 0;
            $max_size = $this->mld_get_max_download_size();

            foreach ( $attachment_ids as $attachment_id ) {
                $file_path = get_attached_file( $attachment_id );
                $file_name = basename( $file_path );
                
                if ( ! $file_path || ! file_exists( $file_path ) ) {
                    continue;
                }

                $file_size = filesize( $file_path );
                if ( $total_size + $file_size > $max_size ) {
                    break; // Stop if we exceed size limit
                }

                // Handle duplicate filenames
                $unique_name = $this->mld_get_unique_filename( $zip, $file_name );
                
                if ( $zip->addFile( $file_path, $unique_name ) ) {
                    $file_count++;
                    $total_size += $file_size;
                }
            }

            $zip->close();

            if ( $file_count === 0 ) {
                unlink( $zip_path );
                wp_send_json_error( __( 'No files could be added to ZIP', 'media-library-downloader' ), 500 );
            }

            // Log the download
            $this->log_download( $attachment_ids, 'zip' );

            wp_send_json_success( array(
                'url' => $zip_url,
                'filename' => $folder_name . '.zip',
                'file_count' => $file_count,
                'single' => false
            ) );
        }

        /**
         * Get maximum download size in bytes
         */
        private function mld_get_max_download_size() {
            $settings = get_option( 'mld_settings', array() );
            $max_size_mb = $settings['max_download_size'] ?? 100;
            $max_size = $max_size_mb * 1024 * 1024; // Convert to bytes
            
            $max_size = apply_filters( 'mld_max_download_size', $max_size );
            return min( $max_size, wp_max_upload_size() * 2 ); // Don't exceed 2x upload limit
        }

        /**
         * Get unique filename for ZIP to handle duplicates
         */
        private function mld_get_unique_filename( $zip, $filename ) {
            $name = pathinfo( $filename, PATHINFO_FILENAME );
            $ext = pathinfo( $filename, PATHINFO_EXTENSION );
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
         */
        private function generate_zip_filename( $timestamp ) {
            $settings = get_option( 'mld_settings', array() );
            $pattern = $settings['zip_filename_pattern'] ?? 'media-library-download-{timestamp}';
            
            $current_user = wp_get_current_user();
            $replacements = array(
                '{timestamp}' => $timestamp,
                '{date}' => date( 'Y-m-d', $timestamp ),
                '{user}' => $current_user->user_login,
                '{userid}' => $current_user->ID,
            );
            
            $filename = str_replace( array_keys( $replacements ), array_values( $replacements ), $pattern );
            return sanitize_file_name( $filename );
        }

        /**
         * Log download activity
         */
        private function log_download( $attachment_ids, $type ) {
            $settings = get_option( 'mld_settings', array() );
            if ( empty( $settings['enable_logging'] ) ) {
                return;
            }

            $current_user = wp_get_current_user();
            $log_entry = array(
                'timestamp' => time(),
                'user' => $current_user->user_login,
                'user_id' => $current_user->ID,
                'file_count' => count( $attachment_ids ),
                'attachment_ids' => $attachment_ids,
                'type' => $type,
                'ip' => $this->get_user_ip(),
            );

            $logs = get_option( 'mld_download_logs', array() );
            $logs[] = $log_entry;

            // Keep only last 1000 entries
            if ( count( $logs ) > 1000 ) {
                $logs = array_slice( $logs, -1000 );
            }

            update_option( 'mld_download_logs', $logs );

            // Hook for developers
            do_action( 'mld_download_logged', $log_entry );
        }

        /**
         * Get user IP address
         */
        private function get_user_ip() {
            if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            }
            return 'unknown';
        }

        /**
         * Schedule automatic cleanup
         */
        public function schedule_cleanup() {
            if ( ! wp_next_scheduled( 'mld_cleanup_temp_files' ) ) {
                $settings = get_option( 'mld_settings', array() );
                $interval = $settings['cleanup_interval'] ?? 24;
                
                wp_schedule_event( time(), 'hourly', 'mld_cleanup_temp_files' );
            }
            
            add_action( 'mld_cleanup_temp_files', array( $this, 'automatic_cleanup' ) );
        }

        /**
         * Automatic cleanup via cron
         */
        public function automatic_cleanup() {
            $settings = get_option( 'mld_settings', array() );
            $cleanup_interval = $settings['cleanup_interval'] ?? 24;
            $cutoff_time = time() - ( $cleanup_interval * HOUR_IN_SECONDS );

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

            // Log cleanup if logging is enabled
            if ( ! empty( $settings['enable_logging'] ) ) {
                $log_entry = array(
                    'timestamp' => time(),
                    'user' => 'system',
                    'user_id' => 0,
                    'file_count' => 0,
                    'attachment_ids' => array(),
                    'type' => 'cleanup',
                    'ip' => 'system',
                );

                $logs = get_option( 'mld_download_logs', array() );
                $logs[] = $log_entry;
                update_option( 'mld_download_logs', $logs );
            }
        }
    }
}

new MLD_Class();
