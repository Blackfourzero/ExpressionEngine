<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use EllisLab\ExpressionEngine\Library\CP\Table;


/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Pages Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Pages_mcp {

	var $page_array		    = array();
	var $pages			    = array();
	var $homepage_display;

	/**
	  *  Constructor
	  */
	function __construct()
	{
		ee()->load->model('pages_model');

		$query = ee()->pages_model->fetch_configuration();

		$default_channel = 0;

		$this->homepage_display = 'not_nested';

		if ($query->num_rows() > 0)
		{
			foreach($query->result_array() as $row)
			{
				$$row['configuration_name'] = $row['configuration_value'];
			}

			$this->homepage_display = $homepage_display;
		}

		ee()->view->header = array(
			'title' => lang('pages_manager'),
			'toolbar_items' => array(
				'settings' => array(
					'href' => ee('CP/URL', 'addons/settings/pages/settings'),
					'title' => lang('settings')
				)
			)
		);
	}

	// --------------------------------------------------------------------

	/**
	  *  Pages Main page
	  */
	function index()
	{
		if ( ! empty($_POST))
		{
			$this->delete();
		}

		$base_url = ee('CP/URL', 'addons/settings/pages');
		$site_id = ee()->config->item('site_id');

		$table = ee('CP/Table', array('autosort' => TRUE, 'autosearch' => FALSE, 'limit' => 20));
		$table->setColumns(
			array(
				'page_name',
				'page_url',
				'manage' => array(
					'type'	=> Table::COL_TOOLBAR
				),
				array(
					'type'	=> Table::COL_CHECKBOX
				)
			)
		);
		$table->setNoResultsText('no_pages');

		$data = array();

		$pages = ee()->config->item('site_pages');
		if ($pages !== FALSE && count($pages[$site_id]['uris']) > 0)
		{
			$entry_ids = array_keys($pages[$site_id]['uris']);
			$entries = ee('Model')->get('ChannelEntry', $entry_ids)
				->fields('entry_id', 'title', 'channel_id')
				->all();

			$titles = $entries->getDictionary('entry_id', 'title');

			foreach($pages[$site_id]['uris'] as $entry_id => $url)
			{
				$checkbox = array(
					'name' => 'selection[]',
					'value' => $entry_id,
					'data'	=> array(
						'confirm' => lang('page') . ': <b>' . htmlentities($titles[$entry_id], ENT_QUOTES) . '</b>'
					)
				);

				$data[] = array(
					'name' => $titles[$entry_id],
					'url' => $url,
					array(
						'toolbar_items' => array(
							'edit' => array(
								'href' => ee('CP/URL', 'publish/edit/entry/' . $entry_id),
								'title' => lang('edit')
							)
						)
					),
					$checkbox
				);
			}
		}

		$table->setData($data);

		$vars['table'] = $table->viewData($base_url);
		$vars['base_url'] = $vars['table']['base_url'];

		$vars['pagination'] = ee('CP/Pagination', $vars['table']['total_rows'])
			->perPage($vars['table']['limit'])
			->currentPage($vars['table']['page'])
			->render($base_url);

		ee()->javascript->set_global('lang.remove_confirm', lang('page') . ': <b>### ' . lang('pages') . '</b>');
		ee()->cp->add_js_script(array(
			'file' => array('cp/confirm_remove'),
		));

		return array(
			'heading' => lang('pages_manager'),
			'body' => ee('View')->make('pages:index')->render($vars)
		);

		return ee('View')->make('pages:index')->render($vars);
	}

	/**
	  *  Delete Pages
	  */
	private function delete()
	{
	    ee()->load->model('pages_model');

		$pages = ee()->config->item('site_pages');
		$urls = array();
		$ids = array();

		foreach ($_POST['selection'] as $id)
		{
			$ids[$id] = $id;
			$urls[] = $pages[ee()->config->item('site_id')]['uris'][$id];
		}

        // Delete Pages & give us the number deleted.
        $delete_pages = ee()->pages_model->delete_site_pages($ids);

		if ($delete_pages !== FALSE)
		{
			ee('Alert')->makeInline('pages-form')
				->asSuccess()
				->withTitle(lang('success'))
				->addToBody(lang('pages_deleted_desc'))
				->addToBody($urls)
				->now();
		}
	}

	/**
	 * Settings
	 */
	public function settings()
	{
		// Create channels dropdown
		$channels = ee('Model')->get('Channel')
			->filter('site_id', ee()->config->item('site_id'))
			->order('channel_title')
			->all();

		$channels_dropdown = array(0 => lang('pages_no_default'));
		foreach ($channels as $channel)
		{
			$channels_dropdown[$channel->channel_id] = $channel->channel_title;
		}

		// Get data for default template dropdowns
		ee()->load->model('template_model');
		$templates = ee()->template_model->get_templates(ee()->config->item('site_id'));

		$templates_dropdown = array(0 => lang('pages_no_default'));
		foreach ($templates->result_array() as $template)
		{
			$templates_dropdown[$template['template_id']] = $template['group_name'].'/'.$template['template_name'];
		}

		ee()->load->add_package_path(PATH_ADDONS.'pages');
		ee()->load->model('pages_model');
		$pages_config = ee()->pages_model->fetch_site_pages_config();

		// Defaults if settings haven't been saved yet
		$config = array(
			'homepage_display' => 'not_nested',
			'default_channel' => 0
		);

		// Bring in settings from DB
		foreach ($pages_config->result_array() as $row)
		{
			$config[$row['configuration_name']] = $row['configuration_value'];
		}

		// Build array to populate multi-dropdown for default templates per channel
		$template_for_channel = array();
		foreach ($channels as $channel)
		{
			$template_for_channel['template_channel_'.$channel->channel_id] = array(
				'label' => $channel->channel_title,
				'choices' => $templates_dropdown,
				'value' => (isset($config['template_channel_'.$channel->channel_id]))
					? (int) $config['template_channel_'.$channel->channel_id] : 0
			);
		}

		$vars['sections'] = array(
			array(
				array(
					'title' => 'pages_display_urls',
					'desc' => 'pages_display_urls_desc',
					'fields' => array(
						'homepage_display' => array(
							'type' => 'inline_radio',
							'choices' => array(
								'nested' => 'pages_url_nested',
								'not_nested' => 'pages_url_not_nested'
							),
							'value' => $config['homepage_display']
						)
					)
				),
				array(
					'title' => 'pages_channel',
					'desc' => 'pages_channel_desc',
					'fields' => array(
						'default_channel' => array(
							'type' => 'select',
							'choices' => $channels_dropdown,
							'value' => (int) $config['default_channel']
						)
					)
				),
				array(
					'title' => 'pages_templates',
					'desc' => 'pages_templates_desc',
					'fields' => array(
						'pages_templates' => array(
							'type' => 'multiselect',
							'choices' => $template_for_channel
						)
					)
				)
			)
		);

		$base_url = ee('CP/URL', 'addons/settings/pages/settings');

		if ( ! empty($_POST))
		{
			if ($this->saveSettings($vars['sections']))
			{
				ee()->view->set_message('success', lang('preferences_updated'), lang('preferences_updated_desc'), TRUE);
			}

			ee()->functions->redirect($base_url);
		}

		$vars['base_url'] = $base_url;
		$vars['cp_page_title'] = lang('pages_settings');
		$vars['save_btn_text'] = 'btn_save_settings';
		$vars['save_btn_text_working'] = 'btn_saving';

		return array(
			'heading' => $vars['cp_page_title'],
			'breadcrumb' => array(
				ee('CP/URL', 'addons/settings/pages')->compile() => lang('pages_manager')
			),
			'body' => ee('View')->make('pages:form')->render($vars)
		);
	}

	/**
	 * Save Pages settings
	 */
	private function saveSettings()
	{
		ee()->load->model('pages_model');

		$data = array();

		foreach($_POST as $key => $value)
		{
			if ($key == 'homepage_display' && in_array($value, array('nested', 'not_nested')))
			{
				$data[$key] = $value;
			}
			elseif (is_numeric($value) && $value != '0' && ($key == 'default_channel' OR substr($key, 0, strlen('template_channel_')) == 'template_channel_'))
			{
				$data[$key] = $value;
			}
		}

		if (count($data) > 0)
		{
			ee()->pages_model->update_pages_configuration($data);
		}

		return TRUE;
	}
}
// END CLASS

/* End of file mcp.pages.php */
/* Location: ./system/expressionengine/modules/pages/mcp.pages.php */
