<?php
if ( ! class_exists( 'MLD_Admin' ) ) {
    /**
     * Admin Settings Class
     */
    class MLD_Admin {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'init_settings' ) );
            add_action( 'wp_ajax_mld_cleanup_temp', array( $this, 'manual_cleanup' ) );
        }

        /**
         * Add admin menu
         */
        public function add_admin_menu() {
            add_options_page(
                __( 'Media Library Downloader Settings', 'media-library-downloader' ),
                __( 'Media Downloader', 'media-library-downloader' ),
                'manage_options',
                'media-library-downloader',
                array( $this, 'settings_page' )
            );
        }

        /**
         * Initialize settings
         */
        public function init_settings() {
            register_setting(
                'mld_settings_group',
                'mld_settings',
                array( $this, 'sanitize_settings' )
            );

            add_settings_section(
                'mld_main_section',
                __( 'Download Settings', 'media-library-downloader' ),
                array( $this, 'main_section_callback' ),
                'media-library-downloader'
            );

            add_settings_field(
                'max_download_size',
                __( 'Maximum Download Size (MB)', 'media-library-downloader' ),
                array( $this, 'max_download_size_callback' ),
                'media-library-downloader',
                'mld_main_section'
            );

            add_settings_field(
                'cleanup_interval',
                __( 'Cleanup Interval (hours)', 'media-library-downloader' ),
                array( $this, 'cleanup_interval_callback' ),
                'media-library-downloader',
                'mld_main_section'
            );

            add_settings_field(
                'enable_logging',
                __( 'Enable Download Logging', 'media-library-downloader' ),
                array( $this, 'enable_logging_callback' ),
                'media-library-downloader',
                'mld_main_section'
            );

            add_settings_field(
                'zip_filename_pattern',
                __( 'ZIP Filename Pattern', 'media-library-downloader' ),
                array( $this, 'zip_filename_pattern_callback' ),
                'media-library-downloader',
                'mld_main_section'
            );
        }

        /**
         * Sanitize settings
         */
        public function sanitize_settings( $input ) {
            $sanitized = array();
            
            $sanitized['max_download_size'] = absint( $input['max_download_size'] ?? 100 );
            if ( $sanitized['max_download_size'] < 1 ) {
                $sanitized['max_download_size'] = 100;
            }
            
            $sanitized['cleanup_interval'] = absint( $input['cleanup_interval'] ?? 24 );
            if ( $sanitized['cleanup_interval'] < 1 ) {
                $sanitized['cleanup_interval'] = 24;
            }
            
            $sanitized['enable_logging'] = ! empty( $input['enable_logging'] );
            
            $sanitized['zip_filename_pattern'] = sanitize_text_field( $input['zip_filename_pattern'] ?? 'media-library-download-{timestamp}' );
            
            return $sanitized;
        }

        /**
         * Main section callback
         */
        public function main_section_callback() {
            echo '<p>' . esc_html__( 'Configure the Media Library Downloader settings below.', 'media-library-downloader' ) . '</p>';
        }

        /**
         * Max download size callback
         */
        public function max_download_size_callback() {
            $settings = get_option( 'mld_settings', array() );
            $value = $settings['max_download_size'] ?? 100;
            echo '<input type="number" name="mld_settings[max_download_size]" value="' . esc_attr( $value ) . '" min="1" max="2048" />';
            echo '<p class="description">' . esc_html__( 'Maximum total size for ZIP downloads in megabytes.', 'media-library-downloader' ) . '</p>';
        }

        /**
         * Cleanup interval callback
         */
        public function cleanup_interval_callback() {
            $settings = get_option( 'mld_settings', array() );
            $value = $settings['cleanup_interval'] ?? 24;
            echo '<input type="number" name="mld_settings[cleanup_interval]" value="' . esc_attr( $value ) . '" min="1" max="168" />';
            echo '<p class="description">' . esc_html__( 'How often to automatically clean up temporary files (in hours).', 'media-library-downloader' ) . '</p>';
        }

        /**
         * Enable logging callback
         */
        public function enable_logging_callback() {
            $settings = get_option( 'mld_settings', array() );
            $checked = ! empty( $settings['enable_logging'] ) ? 'checked' : '';
            echo '<input type="checkbox" name="mld_settings[enable_logging]" value="1" ' . $checked . ' />';
            echo '<p class="description">' . esc_html__( 'Log download activities for analysis and debugging.', 'media-library-downloader' ) . '</p>';
        }

        /**
         * ZIP filename pattern callback
         */
        public function zip_filename_pattern_callback() {
            $settings = get_option( 'mld_settings', array() );
            $value = $settings['zip_filename_pattern'] ?? 'media-library-download-{timestamp}';
            echo '<input type="text" name="mld_settings[zip_filename_pattern]" value="' . esc_attr( $value ) . '" class="regular-text" />';
            echo '<p class="description">' . esc_html__( 'Pattern for ZIP filenames. Use {timestamp}, {date}, {user} as placeholders.', 'media-library-downloader' ) . '</p>';
        }

        /**
         * Settings page
         */
        public function settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            // Handle manual cleanup
            if ( isset( $_POST['manual_cleanup'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'mld_manual_cleanup' ) ) {
                $this->perform_cleanup();
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Temporary files cleaned up successfully.', 'media-library-downloader' ) . '</p></div>';
            }

            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'mld_settings_group' );
                    do_settings_sections( 'media-library-downloader' );
                    submit_button( __( 'Save Settings', 'media-library-downloader' ) );
                    ?>
                </form>

                <hr>

                <h2><?php esc_html_e( 'Maintenance', 'media-library-downloader' ); ?></h2>
                
                <div class="card">
                    <h3><?php esc_html_e( 'Temporary Files Cleanup', 'media-library-downloader' ); ?></h3>
                    <p><?php esc_html_e( 'Clean up temporary ZIP files manually.', 'media-library-downloader' ); ?></p>
                    
                    <?php
                    $temp_files = $this->get_temp_files_info();
                    if ( $temp_files['count'] > 0 ) {
                        echo '<p>' . sprintf(
                            /* translators: %1$d: number of files, %2$s: total size */
                            esc_html__( 'Current temporary files: %1$d files (%2$s)', 'media-library-downloader' ),
                            $temp_files['count'],
                            size_format( $temp_files['size'] )
                        ) . '</p>';
                    } else {
                        echo '<p>' . esc_html__( 'No temporary files found.', 'media-library-downloader' ) . '</p>';
                    }
                    ?>
                    
                    <form method="post">
                        <?php wp_nonce_field( 'mld_manual_cleanup' ); ?>
                        <input type="submit" name="manual_cleanup" class="button button-secondary" 
                               value="<?php esc_attr_e( 'Clean Up Now', 'media-library-downloader' ); ?>" />
                    </form>
                </div>

                <?php if ( $this->is_logging_enabled() ) : ?>
                <div class="card">
                    <h3><?php esc_html_e( 'Download Statistics', 'media-library-downloader' ); ?></h3>
                    <?php $this->display_download_stats(); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Get temp files info
         */
        private function get_temp_files_info() {
            $files = glob( MLD_TEMP_PATH . '*.zip' );
            $count = 0;
            $size = 0;

            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        $count++;
                        $size += filesize( $file );
                    }
                }
            }

            return array(
                'count' => $count,
                'size' => $size
            );
        }

        /**
         * Perform cleanup
         */
        private function perform_cleanup() {
            if ( ! is_dir( MLD_TEMP_PATH ) ) {
                return;
            }

            $files = glob( MLD_TEMP_PATH . '*' );
            if ( ! $files ) {
                return;
            }

            foreach ( $files as $file ) {
                if ( is_file( $file ) && pathinfo( $file, PATHINFO_EXTENSION ) === 'zip' ) {
                    unlink( $file );
                }
            }
        }

        /**
         * Check if logging is enabled
         */
        private function is_logging_enabled() {
            $settings = get_option( 'mld_settings', array() );
            return ! empty( $settings['enable_logging'] );
        }

        /**
         * Display download statistics
         */
        private function display_download_stats() {
            $logs = get_option( 'mld_download_logs', array() );
            
            if ( empty( $logs ) ) {
                echo '<p>' . esc_html__( 'No download activity recorded yet.', 'media-library-downloader' ) . '</p>';
                return;
            }

            $total_downloads = count( $logs );
            $recent_logs = array_slice( array_reverse( $logs ), 0, 10 );

            echo '<p>' . sprintf(
                /* translators: %d: total number of downloads */
                esc_html__( 'Total downloads: %d', 'media-library-downloader' ),
                $total_downloads
            ) . '</p>';

            echo '<h4>' . esc_html__( 'Recent Downloads', 'media-library-downloader' ) . '</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Date', 'media-library-downloader' ) . '</th>';
            echo '<th>' . esc_html__( 'User', 'media-library-downloader' ) . '</th>';
            echo '<th>' . esc_html__( 'Files', 'media-library-downloader' ) . '</th>';
            echo '<th>' . esc_html__( 'Type', 'media-library-downloader' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $recent_logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log['timestamp'] ) ) . '</td>';
                echo '<td>' . esc_html( $log['user'] ) . '</td>';
                echo '<td>' . esc_html( $log['file_count'] ) . '</td>';
                echo '<td>' . esc_html( $log['type'] ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        /**
         * Manual cleanup AJAX handler
         */
        public function manual_cleanup() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Unauthorized', 'media-library-downloader' ) );
            }

            $this->perform_cleanup();
            wp_send_json_success( __( 'Cleanup completed', 'media-library-downloader' ) );
        }
    }

    new MLD_Admin();
}
