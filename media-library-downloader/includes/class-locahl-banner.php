<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/locahl-banner-i18n.php';

if ( ! class_exists( 'MLD_Locahl_Banner' ) ) {
	/**
	 * Branded Locahl marketing banner for wp-admin.
	 */
	class MLD_Locahl_Banner {

		const LIST_PRICE     = '9.99';
		const PROMO_PRICE    = '4.99';
		const PURCHASE_URL   = 'https://www.locahl.app';
		const ASSET_VERSION  = '2.3.3';

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_notices', array( $this, 'render_banner' ), 5 );
			add_action( 'wp_ajax_mld_locahl_dismiss_banner', array( $this, 'dismiss_banner' ) );
		}

		/**
		 * @return string
		 */
		private function get_meta_key() {
			return 'locahl_banner_dismissed_media-library-downloader';
		}

		/**
		 * @return string
		 */
		private function get_banner_lang() {
			return Locahl_Banner_I18n::resolve_lang( get_user_locale() );
		}

		/**
		 * @return string
		 */
		private function get_site_url() {
			return self::PURCHASE_URL . Locahl_Banner_I18n::get_site_path( $this->get_banner_lang() );
		}

		/**
		 * @return bool
		 */
		private function is_promo_active() {
			$start = strtotime( '2026-06-07 00:00:00 Europe/Paris' );
			$end   = $start + ( 30 * DAY_IN_SECONDS );
			$now   = time();
			return $now >= $start && $now < $end;
		}

		/**
		 * @return string
		 */
		private function get_current_price() {
			$amount = $this->is_promo_active() ? self::PROMO_PRICE : self::LIST_PRICE;

			return Locahl_Banner_I18n::format_price( $this->get_banner_lang(), $amount );
		}

		/**
		 * @return bool
		 */
		private function is_plugins_admin_screen() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();

			if ( ! $screen ) {
				return false;
			}

			return in_array( $screen->id, array( 'plugins', 'plugin-install' ), true );
		}

		/**
		 * @param string $hook Admin page hook.
		 */
		public function enqueue_scripts( $hook ) {
			if ( ! in_array( $hook, array( 'plugins.php', 'plugin-install.php' ), true ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), $this->get_meta_key(), true ) ) {
				return;
			}

			wp_enqueue_style(
				'mld-locahl-banner-fonts',
				'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Lexend:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap',
				array(),
				null
			);

			wp_enqueue_style(
				'mld-locahl-banner',
				MLD_URL . 'assets/css/locahl-banner.css',
				array( 'mld-locahl-banner-fonts' ),
				self::ASSET_VERSION
			);

			wp_enqueue_script(
				'mld-locahl-banner',
				MLD_URL . 'assets/js/locahl-banner.js',
				array( 'jquery' ),
				self::ASSET_VERSION,
				true
			);

			wp_localize_script(
				'mld-locahl-banner',
				'mldLocahlBanner',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'action'   => 'mld_locahl_dismiss_banner',
					'nonce'    => wp_create_nonce( 'mld_locahl_dismiss_banner' ),
				)
			);
		}

		/**
		 * @return array<string, mixed>
		 */
		private function get_copy() {
			return Locahl_Banner_I18n::get_copy( $this->get_banner_lang(), $this->get_current_price() );
		}

		/**
		 * Render marketing banner.
		 */
		public function render_banner() {
			if ( ! $this->is_plugins_admin_screen() ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), $this->get_meta_key(), true ) ) {
				return;
			}

			$copy     = $this->get_copy();
			$lang     = $this->get_banner_lang();
			$dir      = Locahl_Banner_I18n::is_rtl( $lang ) ? 'rtl' : 'ltr';
			$site_url = esc_url( $this->get_site_url() );
			$demo_url = esc_url( $this->get_site_url() . '#demo' );
			?>
			<div class="notice locahl-promo-banner mld-locahl-banner">
				<section class="locahl-bento-banner" dir="<?php echo esc_attr( $dir ); ?>" lang="<?php echo esc_attr( $lang ); ?>">
					<button type="button" class="locahl-bento-banner__close" aria-label="<?php echo esc_attr( $copy['dismiss'] ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
					<div class="locahl-bento-banner__glow" aria-hidden="true"></div>
					<div class="locahl-bento-banner__orb" aria-hidden="true"></div>

					<div class="locahl-bento-banner__grid">
						<div class="locahl-bento-banner__content">
							<h2 class="locahl-bento-banner__title">
								<?php echo esc_html( $copy['heading'] ); ?>
								<span><?php echo esc_html( $copy['highlight'] ); ?></span>
							</h2>

							<p class="locahl-bento-banner__body"><?php echo esc_html( $copy['body'] ); ?></p>

							<div class="locahl-bento-banner__actions">
								<a class="locahl-bento-banner__cta" href="<?php echo $site_url; ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $copy['cta'] ); ?>
								</a>
								<a class="locahl-bento-banner__demo" href="<?php echo $demo_url; ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $copy['cta_demo'] ); ?>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
										<path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</a>
							</div>

							<div class="locahl-bento-banner__stats">
								<div>
									<div class="locahl-bento-banner__stat-value"><?php echo esc_html( $copy['stat_users'] ); ?></div>
									<div class="locahl-bento-banner__stat-label"><?php echo esc_html( $copy['stat_users_label'] ); ?></div>
								</div>
								<div class="locahl-bento-banner__stat-divider" aria-hidden="true"></div>
								<div>
									<div class="locahl-bento-banner__stat-value"><?php echo esc_html( $copy['stat_rating'] ); ?></div>
									<div class="locahl-bento-banner__stat-label"><?php echo esc_html( $copy['stat_rating_label'] ); ?></div>
								</div>
							</div>
						</div>

						<div class="locahl-bento-banner__visual">
							<div class="locahl-bento-banner__glass">
								<div class="locahl-bento-banner__mock-head">
									<div class="locahl-bento-banner__dots" aria-hidden="true">
										<span></span><span></span><span></span>
									</div>
									<div class="locahl-bento-banner__env"><?php echo esc_html( $copy['env'] ); ?></div>
								</div>

								<div class="locahl-bento-banner__mock-body">
									<div class="locahl-bento-banner__mock-rows">
										<?php foreach ( $copy['mock_hosts'] as $row ) : ?>
											<div class="locahl-bento-banner__mock-row">
												<span><code><?php echo esc_html( $row['ip'] ); ?></code> <?php echo esc_html( $row['host'] ); ?></span>
												<span class="locahl-bento-banner__mock-toggle" aria-hidden="true"></span>
											</div>
										<?php endforeach; ?>
									</div>

									<div class="locahl-bento-banner__toast">
										<div class="locahl-bento-banner__toast-left">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
												<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<span><?php echo esc_html( $copy['toast'] ); ?></span>
										</div>
										<span class="locahl-bento-banner__toast-time"><?php echo esc_html( $copy['toast_time'] ); ?></span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
			<?php
		}

		/**
		 * Persist banner dismissal.
		 */
		public function dismiss_banner() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'unauthorized', 403 );
			}

			check_ajax_referer( 'mld_locahl_dismiss_banner', 'nonce' );
			update_user_meta( get_current_user_id(), $this->get_meta_key(), '1' );
			wp_send_json_success();
		}
	}

	new MLD_Locahl_Banner();
}
