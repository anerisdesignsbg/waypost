<?php
/**
 * Style switcher for demo visitors: a small "Try a style" panel on the front end.
 * The visitor's choice is kept in a cookie and only changes the demo for them; the
 * site's own settings stay as they are. Docsway is told the chosen style and its
 * accent through the option filter, so everything renders as it would for a site
 * set to that style, including the light / dark switch of Auto light and dark.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Demo_Styles {

	const COOKIE = 'docsway_demo_style';

	/**
	 * Style => array( accent, text on the accent ), matching Docsway_Settings::styles().
	 * Kept here because settings are first read before translations may load.
	 */
	const STYLES = array(
		'theme'   => array( '#2563eb', '#ffffff' ),
		'modern'  => array( '#4f46e5', '#ffffff' ),
		'minimal' => array( '#111827', '#ffffff' ),
		'classic' => array( '#9a3412', '#ffffff' ),
		'light'   => array( '#2563eb', '#ffffff' ),
		'dark'    => array( '#60a5fa', '#111111' ),
		'auto'    => array( '#2563eb', '#ffffff' ),
	);

	public static function init() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		add_filter( 'option_docsway_settings', array( __CLASS__, 'settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'early' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'panel' ) );
	}

	/** The style this visitor chose, or '' for the site's own. */
	public static function chosen() {
		$value = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return isset( self::STYLES[ $value ] ) ? $value : '';
	}

	public static function settings( $value ) {
		$style = self::chosen();
		if ( '' === $style || ! is_array( $value ) ) {
			return $value;
		}
		$value['style']  = $style;
		$value['accent'] = self::STYLES[ $style ][0];
		return $value;
	}

	/** Only on pages that show Docsway. */
	private static function applies() {
		return class_exists( 'Docsway_Frontend' ) && Docsway_Frontend::is_docs_context();
	}

	/**
	 * If a page cache served the page without the visitor's style, switch the body
	 * class and accent before anything is drawn.
	 */
	public static function early() {
		if ( ! self::applies() ) {
			return;
		}
		$js = '(function(){try{var m=document.cookie.match(/(?:^|; )' . self::COOKIE . '=([a-z]+)/),s=m&&m[1],a=' . wp_json_encode( self::STYLES ) . '[s],b=document.body;'
			. 'if(!a||b.classList.contains("docsway-style-"+s))return;'
			. 'b.className=b.className.replace(/\bdocsway-style-[a-z]+\b/g,"");b.classList.add("docsway-style-"+s);'
			. 'var r=document.documentElement.style;r.setProperty("--docsway-accent",a[0]);r.setProperty("--docsway-accent-base",a[0]);r.setProperty("--docsway-on-accent",a[1]);}catch(e){}})();';
		wp_print_inline_script_tag( $js );
	}

	public static function assets() {
		if ( ! self::applies() ) {
			return;
		}
		wp_register_style( 'docsway-demo-styles', false, array(), '1.2.0' );
		wp_enqueue_style( 'docsway-demo-styles' );
		wp_add_inline_style( 'docsway-demo-styles', self::css() );
		wp_register_script( 'docsway-demo-styles', false, array(), '1.2.0', true );
		wp_enqueue_script( 'docsway-demo-styles' );
		wp_add_inline_script( 'docsway-demo-styles', self::js() );
	}

	public static function panel() {
		if ( ! self::applies() || ! class_exists( 'Docsway_Settings' ) ) {
			return;
		}
		$current = Docsway_Settings::get( 'style' );
		$styles  = Docsway_Settings::styles();
		$path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		?>
		<div class="dsd-styles" data-cookie="<?php echo esc_attr( self::COOKIE ); ?>" data-path="<?php echo esc_attr( $path ); ?>">
			<button type="button" class="dsd-styles__open" aria-expanded="false" aria-controls="dsd-styles-panel">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21a9 9 0 1 1 9-9c0 1.7-1.3 3-3 3h-1.8a2 2 0 0 0-1.4 3.4A1.5 1.5 0 0 1 13.7 21H12ZM7.5 11h.01M10 7.5h.01M14 7.5h.01M16.5 11h.01"/></svg>
				<span>Try a style</span>
			</button>
			<div class="dsd-styles__panel" id="dsd-styles-panel" role="dialog" aria-label="Try a style" hidden>
				<p class="dsd-styles__title">Try a style</p>
				<p class="dsd-styles__note">See this demo in each of Docsway's seven styles. Only you see the change.</p>
				<ul class="dsd-styles__list">
					<?php foreach ( self::STYLES as $key => $colours ) : ?>
						<?php $label = isset( $styles[ $key ]['label'] ) ? $styles[ $key ]['label'] : ucfirst( $key ); ?>
						<li>
							<button type="button" class="dsd-styles__item<?php echo $key === $current ? ' is-current' : ''; ?>" data-style="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo $key === $current ? 'true' : 'false'; ?>">
								<span class="dsd-styles__dot dsd-styles__dot--<?php echo esc_attr( $key ); ?>" style="background:<?php echo esc_attr( $colours[0] ); ?>"></span>
								<span><?php echo esc_html( $label ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( '' !== self::chosen() ) : ?>
					<button type="button" class="dsd-styles__reset">Back to the demo's own style</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function css() {
		return '.dsd-styles{position:fixed;left:16px;bottom:16px;z-index:9990;font:14px/1.4 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#111827}'
			. '.dsd-styles button{font:inherit;cursor:pointer}'
			. '.dsd-styles__open{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:0;border-radius:999px;background:#312e81;color:#fff;font-weight:600;box-shadow:0 10px 30px -8px rgba(30,27,75,.55)}'
			. '.dsd-styles__open:hover{background:#4338ca}'
			. '.dsd-styles__open svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}'
			. '.dsd-styles__panel{position:absolute;left:0;bottom:calc(100% + 10px);width:270px;max-width:calc(100vw - 32px);padding:16px;border-radius:16px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 24px 60px -16px rgba(17,24,39,.35)}'
			. '.dsd-styles__panel[hidden]{display:none}'
			. '.dsd-styles__title{margin:0;font-weight:800;font-size:15px}'
			. '.dsd-styles__note{margin:4px 0 12px;color:#6b7280;font-size:13px}'
			. '.dsd-styles__list{list-style:none;margin:0;padding:0;display:grid;gap:4px}'
			. '.dsd-styles__item{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:1px solid transparent;border-radius:10px;background:none;color:#111827;text-align:left}'
			. '.dsd-styles__item:hover{background:#f5f6ff}'
			. '.dsd-styles__item.is-current{border-color:#c7d2fe;background:#eef2ff;font-weight:700}'
			. '.dsd-styles__dot{flex:none;width:18px;height:18px;border-radius:50%;box-shadow:inset 0 0 0 2px rgba(255,255,255,.6),0 0 0 1px rgba(17,24,39,.15)}'
			. '.dsd-styles__dot--dark{box-shadow:inset 0 0 0 4px #111827,0 0 0 1px rgba(17,24,39,.2)}'
			. '.dsd-styles__dot--auto{background:linear-gradient(135deg,#fff 50%,#111827 50%)!important}'
			. '.dsd-styles__reset{margin-top:10px;padding:6px 0;border:0;background:none;color:#4f46e5;text-decoration:underline}'
			. '.dsd-styles .dsd-styles__open:focus-visible,.dsd-styles__item:focus-visible,.dsd-styles__reset:focus-visible{outline:3px solid #a5b4fc;outline-offset:2px}'
			. '@media print{.dsd-styles{display:none}}';
	}

	private static function js() {
		return "(function(){var w=document.querySelector('.dsd-styles');if(!w)return;"
			. "var open=w.querySelector('.dsd-styles__open'),panel=w.querySelector('.dsd-styles__panel'),name=w.getAttribute('data-cookie'),path=w.getAttribute('data-path');"
			. "try{var y=sessionStorage.getItem('docsway-demo-scroll');if(y!==null){sessionStorage.removeItem('docsway-demo-scroll');window.scrollTo(0,parseInt(y,10)||0);}}catch(e){}"
			. "function show(on){panel.hidden=!on;open.setAttribute('aria-expanded',on?'true':'false');}"
			. "function go(value){document.cookie=name+'='+(value||'')+'; path='+path+'; max-age='+(value?2592000:0)+'; SameSite=Lax';try{sessionStorage.setItem('docsway-demo-scroll',String(window.scrollY));}catch(e){}window.location.reload();}"
			. "open.addEventListener('click',function(){show(panel.hidden);});"
			. "w.addEventListener('click',function(e){var b=e.target.closest('[data-style]');if(b){go(b.getAttribute('data-style'));return;}if(e.target.closest('.dsd-styles__reset')){go('');}});"
			. "document.addEventListener('click',function(e){if(!panel.hidden&&!w.contains(e.target))show(false);});"
			. "document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!panel.hidden){show(false);open.focus();}});"
			. '})();';
	}
}
