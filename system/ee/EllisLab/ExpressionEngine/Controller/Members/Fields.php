<?php

namespace EllisLab\ExpressionEngine\Controller\Members;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use CP_Controller;
use EllisLab\ExpressionEngine\Library\CP;
use EllisLab\ExpressionEngine\Library\CP\Table;

use EllisLab\ExpressionEngine\Controller\Members;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Member Fields Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Fields extends Members\Members {

	private $base_url;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		ee()->lang->loadfile('channel');
		$this->base_url = ee('CP/URL', 'members/fields');
		$this->generateSidebar('fields');
	}

	/**
	 * Field List Index
	 */
	public function index()
	{
		$table = ee('CP/Table', array(
			'sortable' => FALSE,
			'reorder' => TRUE,
			'save' => ee('CP/URL', "members/fields/order")
		));

		$table->setColumns(
			array(
				'id' => array(
					'encode' => FALSE
				),
				'label',
				'short_name' => array(
					'encode' => FALSE
				),
				'type',
				'manage' => array(
					'type'	=> Table::COL_TOOLBAR
				),
				array(
					'type'	=> Table::COL_CHECKBOX
				)
			)
		);

		$table->setNoResultsText(
			'no_fields',
			'create_new',
			ee('CP/URL', 'members/fields/create')
		);

		$data = array();
		$fieldData = array();
		$total = ee()->api->get('MemberField')->count();
		$fields = ee()->api->get('MemberField')->order('m_field_order', 'asc')->all();
		$type_map = array(
			'text' => lang('text_input'),
			'textarea' => lang('textarea'),
			'select' => lang('select_dropdown'),
		);

		foreach ($fields as $field)
		{
			$toolbar = array('toolbar_items' => array(
				'edit' => array(
					'href' => ee('CP/URL', 'members/fields/edit/' . $field->m_field_id),
					'title' => strtolower(lang('edit'))
				)
			));

			$columns = array(
				'id' => $field->getId().form_hidden('order[]', $field->getId()),
				'm_field_label' => $field->m_field_label,
				'm_field_name' => "<var>{{$field->m_field_name}}</var>",
				'm_field_type' => $type_map[$field->m_field_type],
				$toolbar,
				array(
					'name' => 'selection[]',
					'value' => $field->m_field_id,
					'data'	=> array(
						'confirm' => lang('field') . ': <b>' . htmlentities($field->m_field_name, ENT_QUOTES) . '</b>'
					)
				)
			);

			$attrs = array();

			if (ee()->session->flashdata('field_id') == $field->getId())
			{
				$attrs = array('class' => 'selected');
			}

			$fieldData[] = array(
				'attrs' => $attrs,
				'columns' => $columns
			);
		}

		$table->setData($fieldData);
		$data['table'] = $table->viewData($this->base_url);
		$data['form_url'] = ee('CP/URL', 'members/fields/delete');
		$data['new'] = ee('CP/URL', 'members/fields/create');
		$base_url = $data['table']['base_url'];

		ee()->javascript->set_global('lang.remove_confirm', lang('member_fields') . ': <b>### ' . lang('member_fields') . '</b>');
		ee()->cp->add_js_script('file', 'cp/confirm_remove');
		ee()->cp->add_js_script('file', 'cp/members/member_field_reorder');
		ee()->cp->add_js_script('file', 'cp/sort_helper');
		ee()->cp->add_js_script('plugin', 'ee_table_reorder');

		$reorder_ajax_fail = ee('Alert')->makeBanner('reorder-ajax-fail')
			->asIssue()
			->canClose()
			->withTitle(lang('member_field_ajax_reorder_fail'))
			->addToBody(lang('member_field_ajax_reorder_fail_desc'));

		ee()->javascript->set_global('member_fields.reorder_url', ee('CP/URL', 'members/fields/order/')->compile());
		ee()->javascript->set_global('alert.reorder_ajax_fail', $reorder_ajax_fail->render());

		ee()->view->base_url = $this->base_url;
		ee()->view->ajax_validate = TRUE;
		ee()->view->cp_page_title = lang('custom_profile_fields');
		ee()->cp->render('members/custom_profile_fields', $data);
	}

	public function create()
	{
		$this->form();
	}

	public function edit($id)
	{
		$this->form($id);
	}

	public function delete()
	{
		$field_ids = ee()->input->post('selection');

		if ( ! is_array($field_ids))
		{
			$field_ids = array($selected);
		}

		$fields = ee('Model')->get('MemberField', $field_ids)->all();
		$field_names = $fields->pluck('field_label');
		$fields->delete();

		ee('Alert')->makeInline('fields')
			->asSuccess()
			->withTitle(lang('success'))
			->addToBody(lang('member_fields_removed_desc'))
			->addToBody($field_names)
			->defer();

		ee()->functions->redirect($this->base_url);
	}

	public function order()
	{
		// Parse out the serialized inputs sent by the JavaScript
		$new_order = array();
		parse_str(ee()->input->post('order'), $new_order);

		if ( ! AJAX_REQUEST OR empty($new_order['order']))
		{
			show_error(lang('unauthorized_access'));
		}

		$fields = ee()->api->get('MemberField')->order('m_field_order', 'asc')->all()->indexBy('m_field_id');

		$order = 1;
		foreach ($new_order['order'] as $field_id)
		{
			if (isset($fields[$field_id]) && $fields[$field_id]->m_field_order != $order)
			{
				$fields[$field_id]->m_field_order = $order;
				$fields[$field_id]->save();
			}

			$order++;
		}

		ee()->output->send_ajax_response(NULL);
		exit;
	}

	private function form($field_id = NULL)
	{
		if ($field_id)
		{
			$field = ee('Model')->get('MemberField', array($field_id))->first();

			ee()->view->save_btn_text = 'btn_edit_field';
			ee()->view->cp_page_title = lang('edit_member_field');
			ee()->view->base_url = ee('CP/URL', 'members/fields/edit/' . $field_id);
		}
		else
		{
			// Only auto-complete field short name for new fields
			ee()->cp->add_js_script('plugin', 'ee_url_title');
			ee()->javascript->output('
				$("input[name=m_field_label]").bind("keyup keydown", function() {
					$(this).ee_url_title("input[name=m_field_name]");
				});
			');

			$field = ee('Model')->make('MemberField');
			$field->field_type = 'text';

			ee()->view->save_btn_text = 'btn_create_field';
			ee()->view->cp_page_title = lang('create_member_field');
			ee()->view->base_url = ee('CP/URL', 'members/fields/create');
		}

		if ( ! $field)
		{
			show_error(lang('unauthorized_access'));
		}

		ee()->lang->loadfile('admin_content');

		$vars['sections'] = array(
			array(
				array(
					'title' => 'type',
					'desc' => '',
					'fields' => array(
						'm_field_type' => array(
							'type' => 'select',
							'choices' => array(
								'text'     => lang('text_input'),
								'textarea' => lang('textarea'),
								'select'   => lang('select_dropdown')
							),
							'group_toggle' => array(
								'text' => 'text',
								'textarea' => 'textarea',
								'select' => 'select'
							),
							'value' => $field->field_type
						)
					)
				),
				array(
					'title' => 'name',
					'fields' => array(
						'm_field_label' => array(
							'type' => 'text',
							'value' => $field->field_label,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'short_name',
					'desc' => 'alphadash_desc',
					'fields' => array(
						'm_field_name' => array(
							'type' => 'text',
							'value' => $field->field_name,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'field_description',
					'desc' => 'field_description_info',
					'fields' => array(
						'm_field_description' => array(
							'type' => 'textarea',
							'value' => $field->field_description
						)
					)
				),
				array(
					'title' => 'require_field',
					'desc' => 'cat_require_field_desc',
					'fields' => array(
						'm_field_required' => array(
							'type' => 'yes_no',
							'value' => $field->field_required
						)
					)
				)
			)
		);

		$visibility['visibility'] = array(
			array(
				'title' => 'is_field_reg',
				'desc' => 'is_field_reg_cont',
				'fields' => array(
					'm_field_reg' => array(
						'type' => 'yes_no',
						'value' => $field->field_reg
					)
				)
			),
			array(
				'title' => 'is_field_public',
				'desc' => 'is_field_public_cont',
				'fields' => array(
					'm_field_public' => array(
						'type' => 'yes_no',
						'value' => $field->field_public
					)
				)
			)
		);


		$settingsForm = $field->getSettingsForm();

		$vars['sections'] += $visibility;
		$vars['sections'] += $settingsForm;
		$settingsFields = array_pop($settingsForm);
		$settingsFields = $settingsFields['settings'];

		if ( ! empty($_POST))
		{
			foreach (array_merge($vars['sections'][0], $vars['sections']['visibility'], $settingsFields) as $section)
			{
				// We have to do this dance of explicitly setting each property
				// so that the MemberField model's magic set method will prefix
				// the properties for us
				foreach ($section['fields'] as $key => $val)
				{
					$field->$key = ee()->input->post($key);
				}
			}

			$result = $field->validate();

			if (AJAX_REQUEST)
			{
				$field = ee()->input->post('ee_fv_field');

				if ($result->hasErrors($field))
				{
					ee()->output->send_ajax_response(array('error' => $result->renderError($field)));
				}
				else
				{
					ee()->output->send_ajax_response('success');
				}
				exit;
			}

			if ($result->isValid())
			{
				$field->save();
				ee()->session->set_flashdata('field_id', $field->field_id);

				ee('Alert')->makeInline('shared-form')
					->asSuccess()
					->withTitle(lang('member_field_saved'))
					->addToBody(lang('member_field_saved_desc'))
					->defer();

				ee()->functions->redirect(ee('CP/URL', '/members/fields'));
			}
			else
			{
				ee()->load->library('form_validation');
				ee()->form_validation->_error_array = $result->renderErrors();
				ee('Alert')->makeInline('shared-form')
					->asIssue()
					->withTitle(lang('member_field_not_saved'))
					->addToBody(lang('member_field_not_saved_desc'))
					->now();
			}
		}

		// These are currently the only fieldtypes we allow; get their settings forms
		foreach (array('text', 'textarea', 'select') as $fieldtype)
		{
			if ($field->field_type != $fieldtype)
			{
				$dummy_field = ee('Model')->make('MemberField');
				$dummy_field->field_type = $fieldtype;
				$vars['sections'] += $dummy_field->getSettingsForm();
			}
		}

		ee()->view->ajax_validate = TRUE;
		ee()->view->save_btn_text_working = 'btn_saving';
		ee()->cp->set_breadcrumb(ee('CP/URL', 'members/fields/edit'), lang('custom_profile_fields'));

		ee()->cp->add_js_script(array(
			'file' => array('cp/form_group'),
		));

		ee()->cp->render('settings/form', $vars);
	}
}
// END CLASS

/* End of file Members.php */
/* Location: ./system/EllisLab/ExpressionEngine/Controller/Members/Fields.php */
