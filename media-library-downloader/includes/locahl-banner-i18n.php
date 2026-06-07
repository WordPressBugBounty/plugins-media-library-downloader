<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Locahl_Banner_I18n' ) ) {
	/**
	 * Banner copy for common WordPress admin locales.
	 */
	class Locahl_Banner_I18n {

		/**
		 * @return string
		 */
		public static function resolve_lang( $locale ) {
			$locale = strtolower( (string) $locale );
			$prefix = strtok( $locale, '_' );

			$map = array(
				'fr' => 'fr',
				'es' => 'es',
				'de' => 'de',
				'it' => 'it',
				'pt' => 'pt',
				'nl' => 'nl',
				'ru' => 'ru',
				'ja' => 'ja',
				'zh' => 'zh',
				'pl' => 'pl',
				'tr' => 'tr',
				'ar' => 'ar',
			);

			if ( isset( $map[ $prefix ] ) ) {
				return $map[ $prefix ];
			}

			return 'en';
		}

		/**
		 * @return string
		 */
		public static function get_site_path( $lang ) {
			return 'fr' === $lang ? '/fr' : '';
		}

		/**
		 * @return bool
		 */
		public static function is_rtl( $lang ) {
			return 'ar' === $lang;
		}

		/**
		 * @param string $lang Language code.
		 * @param string $amount Price amount.
		 * @return string
		 */
		public static function format_price( $lang, $amount ) {
			$comma_langs = array( 'fr', 'de', 'es', 'it', 'pt', 'nl', 'ru', 'pl', 'tr' );

			if ( in_array( $lang, $comma_langs, true ) ) {
				return str_replace( '.', ',', $amount ) . ' €';
			}

			if ( 'ar' === $lang ) {
				return $amount . ' €';
			}

			return '€' . $amount;
		}

		/**
		 * @param string $lang Language code.
		 * @param string $price Formatted price.
		 * @return array<string, mixed>
		 */
		public static function get_copy( $lang, $price ) {
			$strings = self::get_strings();
			$copy    = isset( $strings[ $lang ] ) ? $strings[ $lang ] : $strings['en'];
			$copy['cta'] = sprintf( $copy['cta'], $price );

			return $copy;
		}

		/**
		 * @return array<string, array<string, mixed>>
		 */
		private static function get_strings() {
			$hosts = array(
				array( 'ip' => '127.0.0.1', 'host' => 'api.staging.local' ),
				array( 'ip' => '192.168.1.10', 'host' => 'app.dev.local' ),
				array( 'ip' => '10.0.0.5', 'host' => 'db.internal.local' ),
			);

			return array(
				'en' => array(
					'heading'           => 'Stop fighting',
					'highlight'         => '/etc/hosts',
					'body'              => 'The modern visual hosts file manager for macOS, Windows & Linux. Edit entries, switch environments, and flush DNS without ever touching the terminal.',
					'cta'               => 'Get Locahl — %s',
					'cta_demo'          => 'Try Demo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Active Users',
					'stat_rating'       => '4.9/5',
					'stat_rating_label' => 'User Rating',
					'env'               => 'Production Environment',
					'toast'             => 'DNS Flushed Successfully',
					'toast_time'        => 'Just now',
					'dismiss'           => 'Dismiss banner',
					'mock_hosts'        => $hosts,
				),
				'fr' => array(
					'heading'           => 'Arrêtez de vous battre avec',
					'highlight'         => '/etc/hosts',
					'body'              => 'Le gestionnaire visuel moderne du fichier hosts pour macOS, Windows et Linux. Modifiez vos entrées, changez d\'environnement et videz le DNS sans toucher au terminal.',
					'cta'               => 'Obtenir Locahl — %s',
					'cta_demo'          => 'Voir la démo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Utilisateurs actifs',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Note utilisateurs',
					'env'               => 'Environnement Production',
					'toast'             => 'DNS vidé avec succès',
					'toast_time'        => 'À l\'instant',
					'dismiss'           => 'Fermer la bannière',
					'mock_hosts'        => $hosts,
				),
				'es' => array(
					'heading'           => 'Deja de luchar con',
					'highlight'         => '/etc/hosts',
					'body'              => 'El gestor visual moderno del archivo hosts para macOS, Windows y Linux. Edita entradas, cambia de entorno y vacía el DNS sin usar la terminal.',
					'cta'               => 'Obtener Locahl — %s',
					'cta_demo'          => 'Probar demo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Usuarios activos',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Valoración',
					'env'               => 'Entorno de producción',
					'toast'             => 'DNS vaciado correctamente',
					'toast_time'        => 'Ahora',
					'dismiss'           => 'Cerrar banner',
					'mock_hosts'        => $hosts,
				),
				'de' => array(
					'heading'           => 'Schluss mit dem Ärger um',
					'highlight'         => '/etc/hosts',
					'body'              => 'Der moderne visuelle Hosts-Datei-Manager für macOS, Windows und Linux. Einträge bearbeiten, Umgebungen wechseln und DNS leeren – ganz ohne Terminal.',
					'cta'               => 'Locahl holen — %s',
					'cta_demo'          => 'Demo testen',
					'stat_users'        => '200',
					'stat_users_label'  => 'Aktive Nutzer',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Nutzerbewertung',
					'env'               => 'Produktionsumgebung',
					'toast'             => 'DNS erfolgreich geleert',
					'toast_time'        => 'Gerade eben',
					'dismiss'           => 'Banner schließen',
					'mock_hosts'        => $hosts,
				),
				'it' => array(
					'heading'           => 'Smetti di lottare con',
					'highlight'         => '/etc/hosts',
					'body'              => 'Il moderno gestore visuale del file hosts per macOS, Windows e Linux. Modifica voci, cambia ambiente e svuota il DNS senza usare il terminale.',
					'cta'               => 'Ottieni Locahl — %s',
					'cta_demo'          => 'Prova la demo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Utenti attivi',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Valutazione utenti',
					'env'               => 'Ambiente di produzione',
					'toast'             => 'DNS svuotato con successo',
					'toast_time'        => 'Adesso',
					'dismiss'           => 'Chiudi banner',
					'mock_hosts'        => $hosts,
				),
				'pt' => array(
					'heading'           => 'Pare de lutar com',
					'highlight'         => '/etc/hosts',
					'body'              => 'O gerenciador visual moderno do arquivo hosts para macOS, Windows e Linux. Edite entradas, troque de ambiente e limpe o DNS sem usar o terminal.',
					'cta'               => 'Obter Locahl — %s',
					'cta_demo'          => 'Experimentar demo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Usuários ativos',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Avaliação',
					'env'               => 'Ambiente de produção',
					'toast'             => 'DNS limpo com sucesso',
					'toast_time'        => 'Agora',
					'dismiss'           => 'Fechar banner',
					'mock_hosts'        => $hosts,
				),
				'nl' => array(
					'heading'           => 'Stop met worstelen met',
					'highlight'         => '/etc/hosts',
					'body'              => 'De moderne visuele hosts-bestandsbeheerder voor macOS, Windows en Linux. Bewerk items, wissel van omgeving en leeg DNS zonder de terminal te gebruiken.',
					'cta'               => 'Locahl kopen — %s',
					'cta_demo'          => 'Demo proberen',
					'stat_users'        => '200',
					'stat_users_label'  => 'Actieve gebruikers',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Gebruikersscore',
					'env'               => 'Productieomgeving',
					'toast'             => 'DNS succesvol geleegd',
					'toast_time'        => 'Zojuist',
					'dismiss'           => 'Banner sluiten',
					'mock_hosts'        => $hosts,
				),
				'ru' => array(
					'heading'           => 'Хватит мучиться с',
					'highlight'         => '/etc/hosts',
					'body'              => 'Современный визуальный менеджер файла hosts для macOS, Windows и Linux. Редактируйте записи, переключайте окружения и сбрасывайте DNS без терминала.',
					'cta'               => 'Купить Locahl — %s',
					'cta_demo'          => 'Демо',
					'stat_users'        => '200',
					'stat_users_label'  => 'Активных пользователей',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Оценка пользователей',
					'env'               => 'Продакшн-среда',
					'toast'             => 'DNS успешно сброшен',
					'toast_time'        => 'Только что',
					'dismiss'           => 'Закрыть баннер',
					'mock_hosts'        => $hosts,
				),
				'ja' => array(
					'heading'           => 'もう悩まない',
					'highlight'         => '/etc/hosts',
					'body'              => 'macOS、Windows、Linux 向けのモダンな hosts ファイルビジュアルマネージャー。ターミナル不要でエントリの編集、環境切り替え、DNS キャッシュのフラッシュができます。',
					'cta'               => 'Locahl を入手 — %s',
					'cta_demo'          => 'デモを試す',
					'stat_users'        => '200',
					'stat_users_label'  => 'アクティブユーザー',
					'stat_rating'       => '4.9/5',
					'stat_rating_label' => 'ユーザー評価',
					'env'               => '本番環境',
					'toast'             => 'DNS をフラッシュしました',
					'toast_time'        => 'たった今',
					'dismiss'           => 'バナーを閉じる',
					'mock_hosts'        => $hosts,
				),
				'zh' => array(
					'heading'           => '告别',
					'highlight'         => '/etc/hosts',
					'body'              => '适用于 macOS、Windows 和 Linux 的现代化 hosts 文件可视化管理工具。无需终端即可编辑条目、切换环境并刷新 DNS。',
					'cta'               => '获取 Locahl — %s',
					'cta_demo'          => '试用演示',
					'stat_users'        => '200',
					'stat_users_label'  => '活跃用户',
					'stat_rating'       => '4.9/5',
					'stat_rating_label' => '用户评分',
					'env'               => '生产环境',
					'toast'             => 'DNS 已成功刷新',
					'toast_time'        => '刚刚',
					'dismiss'           => '关闭横幅',
					'mock_hosts'        => $hosts,
				),
				'pl' => array(
					'heading'           => 'Przestań walczyć z',
					'highlight'         => '/etc/hosts',
					'body'              => 'Nowoczesny wizualny menedżer pliku hosts dla macOS, Windows i Linux. Edytuj wpisy, przełączaj środowiska i czyść DNS bez terminala.',
					'cta'               => 'Kup Locahl — %s',
					'cta_demo'          => 'Wypróbuj demo',
					'stat_users'        => '200',
					'stat_users_label'  => 'Aktywni użytkownicy',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Ocena użytkowników',
					'env'               => 'Środowisko produkcyjne',
					'toast'             => 'DNS wyczyszczony pomyślnie',
					'toast_time'        => 'Przed chwilą',
					'dismiss'           => 'Zamknij baner',
					'mock_hosts'        => $hosts,
				),
				'tr' => array(
					'heading'           => 'Artık uğraşmayın:',
					'highlight'         => '/etc/hosts',
					'body'              => 'macOS, Windows ve Linux için modern görsel hosts dosyası yöneticisi. Terminal kullanmadan girişleri düzenleyin, ortamları değiştirin ve DNS önbelleğini temizleyin.',
					'cta'               => 'Locahl edinin — %s',
					'cta_demo'          => 'Demoyu dene',
					'stat_users'        => '200',
					'stat_users_label'  => 'Aktif kullanıcı',
					'stat_rating'       => '4,9/5',
					'stat_rating_label' => 'Kullanıcı puanı',
					'env'               => 'Üretim ortamı',
					'toast'             => 'DNS başarıyla temizlendi',
					'toast_time'        => 'Az önce',
					'dismiss'           => 'Bannerı kapat',
					'mock_hosts'        => $hosts,
				),
				'ar' => array(
					'heading'           => 'توقف عن معاناة',
					'highlight'         => '/etc/hosts',
					'body'              => 'مدير ملف hosts المرئي الحديث لأنظمة macOS وWindows وLinux. عدّل الإدخالات وبدّل البيئات وامسح DNS دون استخدام الطرفية.',
					'cta'               => 'احصل على Locahl — %s',
					'cta_demo'          => 'جرّب العرض',
					'stat_users'        => '200',
					'stat_users_label'  => 'مستخدم نشط',
					'stat_rating'       => '4.9/5',
					'stat_rating_label' => 'تقييم المستخدمين',
					'env'               => 'بيئة الإنتاج',
					'toast'             => 'تم مسح DNS بنجاح',
					'toast_time'        => 'الآن',
					'dismiss'           => 'إغلاق الشعار',
					'mock_hosts'        => $hosts,
				),
			);
		}
	}
}
