<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
include('include/views/js/administration.general.triggerDisplayingOptions.js.php');


$triggerDOFormList = new CFormList('scriptsTab');

$severityForm = new CForm();
$severityForm->setName('triggerDisplayingOptions');
$severityForm->addVar('form', $data['form']);
$severityForm->addVar('form_refresh', $data['form_refresh'] + 1);

$headerDiv = new CDiv(_('Colour'), 'inlineblock trigger_displaying_form_col');
$headerDiv->addStyle('margin-left: 2px;');
$triggerDOFormList->addRow(SPACE, array($headerDiv, _('Blinking')));

// Unacknowledged problem events
$triggerDOFormList->addRow(
	_('Unacknowledged PROBLEM events'),
	array(
		new CDiv(
			new CColor('problem_unack_color', $data['problem_unack_color']),
			'inlineblock trigger_displaying_form_col'
		),
		new CCheckBox(
			'problem_unack_style',
			$data['problem_unack_style'] == 1,
			null,
			1
		)
	)
);

// Acknowledged problem events
$triggerDOFormList->addRow(
	_('Acknowledged PROBLEM events'),
	array(
		new CDiv(
			new CColor('problem_ack_color', $data['problem_ack_color']),
			'inlineblock trigger_displaying_form_col'
		),
		new CCheckBox(
			'problem_ack_style',
			$data['problem_ack_style'] == 1,
			null,
			1
		)
	)
);

// Unacknowledged recovery events
$triggerDOFormList->addRow(
	_('Unacknowledged OK events'),
	array(
		new CDiv(
			new CColor('ok_unack_color', $data['ok_unack_color']),
			'inlineblock trigger_displaying_form_col'
		),
		new CCheckBox(
			'ok_unack_style',
			$data['ok_unack_style'] == 1,
			null,
			1
		)
	)
);

// Acknowledged recovery events
$triggerDOFormList->addRow(
	_('Acknowledged OK events'),
	array(
		new CDiv(
			new CColor('ok_ack_color', $data['ok_ack_color']),
			'inlineblock trigger_displaying_form_col'
		),
		new CCheckBox(
			'ok_ack_style',
			$data['ok_ack_style'] == 1,
			null,
			1
		)
	)
);


// some air between the sections
$triggerDOFormList->addRow(BR());

// Display OK triggers
$okPeriodTextBox = new CTextBox('ok_period', $data['ok_period']);
$okPeriodTextBox->addStyle('width: 4em;');
$okPeriodTextBox->setAttribute('maxlength', '6');
$triggerDOFormList->addRow(_('Display OK triggers for'), array($okPeriodTextBox, SPACE, _('seconds')));

// Triggers blink on status change
$okPeriodTextBox = new CTextBox('blink_period', $data['blink_period']);
$okPeriodTextBox->addStyle('width: 4em;');
$okPeriodTextBox->setAttribute('maxlength', '6');
$triggerDOFormList->addRow(_('On status change triggers blink for'), array($okPeriodTextBox, SPACE, _('seconds')));

$severityView = new CTabView();
$severityView->addTab('triggerdo', _('Trigger displaying options'), $triggerDOFormList);
$severityForm->addItem($severityView);

// Footer
$footer = makeFormFooter(array(new CSubmit('save', _('Save'))),	new CButton('resetDefaults', _('Reset defaults')));
$severityForm->addItem($footer);


return $severityForm;
?>