<?php
/**
 * Divi module classes. Loaded only when Divi's builder is ready.
 */

defined( 'ABSPATH' ) || exit;

trait Docsway_Divi_Common {

	/*
	 * ET_Builder_Element already declares $vb_support, $type and $module_credits.
	 * A trait may not redeclare them with different defaults (PHP fatal error),
	 * so they are set here instead. Divi calls init() from its constructor,
	 * before it reads any of them.
	 */
	public function init() {
		$this->name             = $this->docsway_name();
		$this->main_css_element = '%%order_class%%';
		$this->vb_support       = 'on';
		$this->type             = 'child'; // Matches the original working build; avoids notices that break Divi saves.
		$this->module_credits   = array(
			'module_uri' => 'https://anerisdesigns.com/docsway',
			'author'     => 'Aneris Designs',
			'author_uri' => 'https://anerisdesigns.com',
		);
	}

	public function get_fields() {
		return array(
			'admin_label' => array(
				'label'       => et_builder_i18n( 'Admin Label' ),
				'type'        => 'text',
				'toggle_slug' => 'admin_label',
			),
		);
	}

	/** The Visual Builder gets a placeholder; the real output renders on the front end. */
	public function render( $attrs, $content, $render_slug ) {
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return '<div class="docsway-divi-placeholder">' . esc_html( $this->docsway_name() ) . ' · ' . esc_html__( 'preview on the front end', 'aneris-docsway' ) . '</div>';
		}
		return $this->docsway_output();
	}
}

class Docsway_Divi_Menu extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_menu';
	protected function docsway_name() { return __( 'Docsway Docs Menu', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::menu(); }
}

class Docsway_Divi_Search extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_search';
	protected function docsway_name() { return __( 'Docsway Docs Search', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::search_box(); }
}

class Docsway_Divi_Home extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_search_home';
	protected function docsway_name() { return __( 'Docsway Search Home', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::search_home(); }
}

class Docsway_Divi_Toc extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_toc';
	protected function docsway_name() { return __( 'Docsway On This Page', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::toc(); }
}

class Docsway_Divi_Pager extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_prev_next';
	protected function docsway_name() { return __( 'Docsway Previous / Next', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::prev_next(); }
}

class Docsway_Divi_Crumbs extends ET_Builder_Module {
	use Docsway_Divi_Common;
	public $slug = 'et_pb_docsway_breadcrumbs';
	protected function docsway_name() { return __( 'Docsway Breadcrumbs', 'aneris-docsway' ); }
	protected function docsway_output() { return Docsway_Render::breadcrumbs(); }
}

// Divi registers a module when it is constructed, so each one is created once here.
// This file only loads on et_builder_ready, when ET_Builder_Module is available.
new Docsway_Divi_Menu();
new Docsway_Divi_Search();
new Docsway_Divi_Home();
new Docsway_Divi_Toc();
new Docsway_Divi_Pager();
new Docsway_Divi_Crumbs();
