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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


function graphType($type = null) {
	$types = array(
		GRAPH_TYPE_NORMAL => _('Normal'),
		GRAPH_TYPE_STACKED => _('Stacked'),
		GRAPH_TYPE_PIE => _('Pie'),
		GRAPH_TYPE_EXPLODED => _('Exploded')
	);

	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function graph_item_type2str($type) {
	switch ($type) {
		case GRAPH_ITEM_SUM:
			return _('Graph sum');
		case GRAPH_ITEM_SIMPLE:
		default:
			return _('Simple');
	}
}

function graph_item_drawtypes() {
	return array(
		GRAPH_ITEM_DRAWTYPE_LINE,
		GRAPH_ITEM_DRAWTYPE_FILLED_REGION,
		GRAPH_ITEM_DRAWTYPE_BOLD_LINE,
		GRAPH_ITEM_DRAWTYPE_DOT,
		GRAPH_ITEM_DRAWTYPE_DASHED_LINE,
		GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE
	);
}

function graph_item_drawtype2str($drawtype) {
	switch ($drawtype) {
		case GRAPH_ITEM_DRAWTYPE_LINE:
			return _('Line');
		case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
			return _('Filled region');
		case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
			return _('Bold line');
		case GRAPH_ITEM_DRAWTYPE_DOT:
			return _('Dot');
		case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
			return _('Dashed line');
		case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
			return _('Gradient line');
		default:
			return _('Unknown');
	}
}

function graph_item_calc_fnc2str($calc_fnc) {
	switch ($calc_fnc) {
		case 0:
			return _('Count');
		case CALC_FNC_ALL:
			return _('all');
		case CALC_FNC_MIN:
			return _('min');
		case CALC_FNC_MAX:
			return _('max');
		case CALC_FNC_LST:
			return _('last');
		case CALC_FNC_AVG:
		default:
			return _('avg');
	}
}

function getGraphDims($graphid = null) {
	$graphDims = array();

	$graphDims['shiftYtop'] = 35;
	if (is_null($graphid)) {
		$graphDims['graphHeight'] = 200;
		$graphDims['graphtype'] = 0;

		if (GRAPH_YAXIS_SIDE_DEFAULT == 0) {
			$graphDims['shiftXleft'] = 85;
			$graphDims['shiftXright'] = 30;
		}
		else {
			$graphDims['shiftXleft'] = 30;
			$graphDims['shiftXright'] = 85;
		}

		return $graphDims;
	}

	// zoom featers
	$dbGraphs = DBselect(
		'SELECT MAX(g.graphtype) AS graphtype,MIN(gi.yaxisside) AS yaxissidel,MAX(gi.yaxisside) AS yaxissider,MAX(g.height) AS height'.
		' FROM graphs g,graphs_items gi'.
		' WHERE g.graphid='.$graphid.
			' AND gi.graphid=g.graphid'
	);
	if ($graph = DBfetch($dbGraphs)) {
		$yaxis = $graph['yaxissider'];
		$yaxis = ($graph['yaxissidel'] == $yaxis) ? $yaxis : 2;

		$graphDims['yaxis'] = $yaxis;
		$graphDims['graphtype'] = $graph['graphtype'];
		$graphDims['graphHeight'] = $graph['height'];
	}

	if ($yaxis == 2) {
		$graphDims['shiftXleft'] = 85;
		$graphDims['shiftXright'] = 85;
	}
	elseif ($yaxis == 0) {
		$graphDims['shiftXleft'] = 85;
		$graphDims['shiftXright'] = 30;
	}
	else {
		$graphDims['shiftXleft'] = 30;
		$graphDims['shiftXright'] = 85;
	}

	return $graphDims;
}

function get_graphs_by_hostid($hostid) {
	return DBselect(
		'SELECT DISTINCT g.*'.
		' FROM graphs g,graphs_items gi,items i'.
		' WHERE g.graphid=gi.graphid'.
			' AND gi.itemid=i.itemid'.
			' AND i.hostid='.$hostid
	);
}

function get_realhosts_by_graphid($graphid) {
	$graph = get_graph_by_graphid($graphid);
	if (!empty($graph['templateid'])) {
		return get_realhosts_by_graphid($graph['templateid']);
	}
	return get_hosts_by_graphid($graphid);
}

function get_hosts_by_graphid($graphid) {
	return DBselect(
		'SELECT DISTINCT h.*'.
		' FROM graphs_items gi,items i,hosts h'.
		' WHERE h.hostid=i.hostid'.
			' AND gi.itemid=i.itemid'.
			' AND gi.graphid='.$graphid
	);
}

/*
 * Description:
 *     Return the time of the 1st appearance of items included in graph in trends
 * Comment:
 *	sql is split to many sql's to optimize search on history tables
 */
function get_min_itemclock_by_graphid($graphid) {
	$itemids = array();
	$dbItems = DBselect(
		'SELECT DISTINCT gi.itemid'.
		' FROM graphs_items gi'.
		' WHERE gi.graphid='.$graphid
	);
	while ($item = DBfetch($dbItems)) {
		$itemids[$item['itemid']] = $item['itemid'];
	}

	return get_min_itemclock_by_itemid($itemids);
}

/*
 * Description:
 *     Return the time of the 1st appearance of item in trends
 */
function get_min_itemclock_by_itemid($itemids) {
	zbx_value2array($itemids);
	$min = null;
	$result = time() - SEC_PER_YEAR;

	$items_by_type = array(
		ITEM_VALUE_TYPE_FLOAT => array(),
		ITEM_VALUE_TYPE_STR =>  array(),
		ITEM_VALUE_TYPE_LOG => array(),
		ITEM_VALUE_TYPE_UINT64 => array(),
		ITEM_VALUE_TYPE_TEXT => array()
	);

	$dbItems = DBselect(
		'SELECT i.itemid,i.value_type'.
		' FROM items i'.
		' WHERE '.DBcondition('i.itemid', $itemids)
	);

	while ($item = DBfetch($dbItems)) {
		$items_by_type[$item['value_type']][$item['itemid']] = $item['itemid'];
	}

	// data for ITEM_VALUE_TYPE_FLOAT and ITEM_VALUE_TYPE_UINT64 can be stored in trends tables or history table
	// get max trends and history values for such type items to find out in what tables to look for data
	$sql_from = 'history';
	$sql_from_num = '';

	if (!empty($items_by_type[ITEM_VALUE_TYPE_FLOAT]) || !empty($items_by_type[ITEM_VALUE_TYPE_UINT64])) {
		$itemids_numeric = zbx_array_merge($items_by_type[ITEM_VALUE_TYPE_FLOAT], $items_by_type[ITEM_VALUE_TYPE_UINT64]);

		$sql = 'SELECT MAX(i.history) AS history,MAX(i.trends) AS trends'.
				' FROM items i'.
				' WHERE '.DBcondition('i.itemid', $itemids_numeric);
		if ($table_for_numeric = DBfetch(DBselect($sql))) {
			$sql_from_num = ($table_for_numeric['history'] > $table_for_numeric['trends']) ? 'history' : 'trends';
			$result = time() - (SEC_PER_DAY * max($table_for_numeric['history'], $table_for_numeric['trends']));
		}
	}

	foreach ($items_by_type as $type => $items) {
		if (empty($items)) {
			continue;
		}

		switch($type) {
			case ITEM_VALUE_TYPE_FLOAT:
				$sql_from = $sql_from_num;
				break;
			case ITEM_VALUE_TYPE_STR:
				$sql_from = 'history_str';
				break;
			case ITEM_VALUE_TYPE_LOG:
				$sql_from = 'history_log';
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$sql_from = $sql_from_num.'_uint';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$sql_from = 'history_text';
				break;
			default:
				$sql_from = 'history';
		}

		$res = DBselect(
			'SELECT ht.itemid,MIN(ht.clock) AS min_clock'.
			' FROM '.$sql_from.' ht'.
			' WHERE '.DBcondition('ht.itemid', $itemids).
			' GROUP BY ht.itemid'
		);
		while ($min_tmp = DBfetch($res)) {
			$min = (is_null($min)) ? $min_tmp['min_clock'] : min($min, $min_tmp['min_clock']);
		}
	}
	$result = is_null($min) ? $result : $min;

	return $result;
}

function get_graph_by_graphid($graphid) {
	$dbGraphs = DBselect('SELECT g.* FROM graphs g WHERE g.graphid='.$graphid);
	$dbGraphs = DBfetch($dbGraphs);
	if (!empty($dbGraphs)) {
		return $dbGraphs;
	}

	error(_s('No graph item with graphid "%s".', $graphid));

	return false;
}

/**
 * Replace items for specified host.
 *
 * @param $gitems
 * @param $dest_hostid
 * @param bool $error if false error won't be thrown when item does not exist
 * @return array|bool
 */
function get_same_graphitems_for_host($gitems, $dest_hostid, $error = true) {
	$result = array();

	foreach ($gitems as $gitem) {
		$dbItem = DBfetch(DBselect(
			'SELECT dest.itemid,src.key_'.
			' FROM items dest,items src'.
			' WHERE dest.key_=src.key_'.
				' AND dest.hostid='.$dest_hostid.
				' AND src.itemid='.$gitem['itemid']
		));

		if ($dbItem) {
			$gitem['itemid'] = $dbItem['itemid'];
			$gitem['key_'] = $dbItem['key_'];
		}
		elseif ($error) {
			$item = get_item_by_itemid($gitem['itemid']);
			$host = get_host_by_hostid($dest_hostid);
			error(_s('Missing key "%1$s" for host "%2$s".', $item['key_'], $host['host']));
			return false;
		}
		else {
			continue;
		}
		$result[] = $gitem;
	}

	return $result;
}

/**
 * Copy specified graph to specified host.
 *
 * @param $graphid
 * @param $hostid
 * @return array|bool
 */
function copy_graph_to_host($graphid, $hostid) {
	$graphs = API::Graph()->get(array(
		'graphids' => $graphid,
		'output' => API_OUTPUT_EXTEND,
		'selectGraphItems' => API_OUTPUT_EXTEND
	));
	$graph = reset($graphs);

	$new_gitems = get_same_graphitems_for_host($graph['gitems'], $hostid);

	if (!$new_gitems) {
		$host = get_host_by_hostid($hostid);
		info(_s('Skipped copying of graph "%1$s" to host "%2$s".', $graph['name'], $host['host']));
		return false;
	}

	// retrieve actual ymax_itemid and ymin_itemid
	if ($graph['ymax_itemid']) {
		if ($itemid = get_same_item_for_host($graph['ymax_itemid'], $hostid)) {
			$graph['ymax_itemid'] = $itemid;
		};
	}

	if ($graph['ymin_itemid']) {
		if ($itemid = get_same_item_for_host($graph['ymin_itemid'], $hostid)) {
			$graph['ymin_itemid'] = $itemid;
		}
	}

	$graph['gitems'] = $new_gitems;
	$result = API::Graph()->create($graph);

	return $result;
}

function navigation_bar_calc($idx = null, $idx2 = 0, $update = false) {
	if (!is_null($idx)) {
		if ($update) {
			if (isset($_REQUEST['period']) && $_REQUEST['period'] >= ZBX_MIN_PERIOD) {
				CProfile::update($idx.'.period', $_REQUEST['period'], PROFILE_TYPE_INT, $idx2);
			}
			if (isset($_REQUEST['stime'])) {
				CProfile::update($idx.'.stime', $_REQUEST['stime'], PROFILE_TYPE_STR, $idx2);
			}
		}
		$_REQUEST['period'] = get_request('period', CProfile::get($idx.'.period', ZBX_PERIOD_DEFAULT, $idx2));
		$_REQUEST['stime'] = get_request('stime', CProfile::get($idx.'.stime', null, $idx2));
	}
	$_REQUEST['period'] = get_request('period', ZBX_PERIOD_DEFAULT);
	$_REQUEST['stime'] = get_request('stime', null);

	if ($_REQUEST['period'] < ZBX_MIN_PERIOD) {
		show_message(_n('Warning. Minimum time period to display is %1$s hour.',
			'Warning. Minimum time period to display is %1$s hours.',
			(int) ZBX_MIN_PERIOD / SEC_PER_HOUR
		));
		$_REQUEST['period'] = ZBX_MIN_PERIOD;
	}
	elseif ($_REQUEST['period'] > ZBX_MAX_PERIOD) {
		show_message(_n('Warning. Maximum time period to display is %1$s day.',
			'Warning. Maximum time period to display is %1$s days.',
			(int) ZBX_MAX_PERIOD / SEC_PER_DAY
		));
		$_REQUEST['period'] = ZBX_MAX_PERIOD;
	}

	if (isset($_REQUEST['stime'])) {
		$time = zbxDateToTime($_REQUEST['stime']);
		if (($time + $_REQUEST['period']) > time()) {
			$_REQUEST['stime'] = date('YmdHis', time() - $_REQUEST['period']);
		}
	}
	else {
		$_REQUEST['stime'] = date('YmdHis', time() - $_REQUEST['period']);
	}
	return $_REQUEST['period'];
}

function get_next_color($palettetype = 0) {
	static $prev_color = array('dark' => true, 'color' => 0, 'grad' => 0);

	switch ($palettetype) {
		case 1:
			$grad = array(200, 150, 255, 100, 50, 0);
			break;
		case 2:
			$grad = array(100, 50, 200, 150, 250, 0);
			break;
		case 0:
		default:
			$grad = array(255, 200, 150, 100, 50, 0);
			break;
	}

	$set_grad = $grad[$prev_color['grad']];

	$r = $g = $b = (100 < $set_grad) ? 0 : 255;

	switch ($prev_color['color']) {
		case 0:
			$r = $set_grad;
			break;
		case 1:
			$g = $set_grad;
			break;
		case 2:
			$b = $set_grad;
			break;
		case 3:
			$r = $b = $set_grad;
			break;
		case 4:
			$g = $b = $set_grad;
			break;
		case 5:
			$r = $g = $set_grad;
			break;
		case 6:
			$r = $g = $b = $set_grad;
			break;
	}

	$prev_color['dark'] = !$prev_color['dark'];
	if ($prev_color['color'] == 6) {
		$prev_color['grad'] = ($prev_color['grad'] + 1) % 6;
	}
	$prev_color['color'] = ($prev_color['color'] + 1) % 7;

	return array($r, $g, $b);
}

function get_next_palette($palette = 0, $palettetype = 0) {
	static $prev_color = array(0, 0, 0, 0);

	switch ($palette) {
		case 0:
			$palettes = array(
				array(150, 0, 0), array(0, 100, 150), array(170, 180, 180), array(152, 100, 0), array(130, 0, 150),
				array(0, 0, 150), array(200, 100, 50), array(250, 40, 40), array(50, 150, 150), array(100, 150, 0)
			);
			break;
		case 1:
			$palettes = array(
				array(0, 100, 150), array(153, 0, 30), array(100, 150, 0), array(130, 0, 150), array(0, 0, 100),
				array(200, 100, 50), array(152, 100, 0), array(0, 100, 0), array(170, 180, 180), array(50, 150, 150)
			);
			break;
		case 2:
			$palettes = array(
				array(170, 180, 180), array(152, 100, 0), array(50, 200, 200), array(153, 0, 30), array(0, 0, 100),
				array(100, 150, 0), array(130, 0, 150), array(0, 100, 150), array(200, 100, 50), array(0, 100, 0)
			);
			break;
		case 3:
		default:
			return get_next_color($palettetype);
	}

	if (isset($palettes[$prev_color[$palette]])) {
		$result = $palettes[$prev_color[$palette]];
	}
	else {
		return get_next_color($palettetype);
	}

	switch ($palettetype) {
		case 0:
			$diff = 0;
			break;
		case 1:
			$diff = -50;
			break;
		case 2:
			$diff = 50;
			break;
	}

	foreach ($result as $n => $color) {
		if (($color + $diff) < 0) {
			$result[$n] = 0;
		}
		elseif (($color + $diff) > 255) {
			$result[$n] = 255;
		}
		else {
			$result[$n] += $diff;
		}
	}
	$prev_color[$palette]++;

	return $result;
}

function imageDiagonalMarks($im,$x, $y, $offset, $color) {
	global $colors;

	$gims = array(
		'lt' => array(0, 0, -9, 0, -9, -3, -3, -9, 0, -9),
		'rt' => array(0, 0, 9, 0, 9, -3, 3,-9, 0, -9),
		'lb' => array(0, 0, -9, 0, -9, 3, -3, 9, 0, 9),
		'rb' => array(0, 0, 9, 0, 9, 3, 3, 9, 0, 9)
	);

	foreach ($gims['lt'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['lt'][$num] = $px + $x - $offset;
		}
		else {
			$gims['lt'][$num] = $px + $y - $offset;
		}
	}

	foreach ($gims['rt'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['rt'][$num] = $px + $x + $offset;
		}
		else {
			$gims['rt'][$num] = $px + $y - $offset;
		}
	}

	foreach ($gims['lb'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['lb'][$num] = $px + $x - $offset;
		}
		else {
			$gims['lb'][$num] = $px + $y + $offset;
		}
	}

	foreach ($gims['rb'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['rb'][$num] = $px + $x + $offset;
		}
		else {
			$gims['rb'][$num] = $px + $y + $offset;
		}
	}

	imagefilledpolygon($im, $gims['lt'], 5, $color);
	imagepolygon($im, $gims['lt'], 5, $colors['Dark Red']);

	imagefilledpolygon($im, $gims['rt'], 5, $color);
	imagepolygon($im, $gims['rt'], 5, $colors['Dark Red']);

	imagefilledpolygon($im, $gims['lb'], 5, $color);
	imagepolygon($im, $gims['lb'], 5, $colors['Dark Red']);

	imagefilledpolygon($im, $gims['rb'], 5, $color);
	imagepolygon($im, $gims['rb'], 5, $colors['Dark Red']);
}

function imageVerticalMarks($im, $x, $y, $offset, $color, $marks = 'tlbr') {
	global $colors;

	$polygons = 5;
	$gims = array(
		't' => array(0, 0, -6, -6, -3, -9, 3, -9, 6, -6),
		'l' => array(0, 0, -6, 6, -9, 3, -9, -3, -6, -6),
		'b' => array(0, 0, 6, 6, 3, 9, -3, 9, -6, 6),
		'r' => array(0, 0, 6, -6, 9, -3, 9, 3, 6, 6)
	);

	foreach ($gims['t'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['t'][$num] = $px + $x;
		}
		else {
			$gims['t'][$num] = $px + $y - $offset;
		}
	}

	foreach ($gims['l'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['l'][$num] = $px + $x - $offset;
		}
		else {
			$gims['l'][$num] = $px + $y;
		}
	}

	foreach ($gims['b'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['b'][$num] = $px + $x;
		}
		else {
			$gims['b'][$num] = $px + $y + $offset;
		}
	}

	foreach ($gims['r'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['r'][$num] = $px + $x + $offset;
		}
		else {
			$gims['r'][$num] = $px + $y;
		}
	}

	if (strpos($marks, 't') !== false) {
		imagefilledpolygon($im, $gims['t'], $polygons, $color);
		imagepolygon($im, $gims['t'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'l') !== false) {
		imagefilledpolygon($im, $gims['l'], $polygons, $color);
		imagepolygon($im, $gims['l'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'b') !== false) {
		imagefilledpolygon($im, $gims['b'], $polygons, $color);
		imagepolygon($im, $gims['b'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'r') !== false) {
		imagefilledpolygon($im, $gims['r'], $polygons, $color);
		imagepolygon($im, $gims['r'], $polygons, $colors['Dark Red']);
	}
}

function imageText($image, $fontsize, $angle, $x, $y, $color, $string) {
	$gdinfo = gd_info();

	if ($gdinfo['FreeType Support'] && function_exists('imagettftext')) {
		if ((preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && $angle != 0) || ZBX_FONT_NAME == ZBX_GRAPH_FONT_NAME) {
			$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
			imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
		}
		elseif ($angle == 0) {
			$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
			imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
		}
		else {
			$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
			$size = imageTextSize($fontsize, 0, $string);

			$imgg = imagecreatetruecolor($size['width'] + 1, $size['height']);
			$transparentColor = imagecolorallocatealpha($imgg, 200, 200, 200, 127);
			imagefill($imgg, 0, 0, $transparentColor);
			imagettftext($imgg, $fontsize, 0, 0, $size['height'], $color, $ttf, $string);

			$imgg = imagerotate($imgg, $angle, $transparentColor);
			ImageAlphaBlending($imgg, false);
			imageSaveAlpha($imgg, true);
			imagecopy($image, $imgg, $x - $size['height'], $y - $size['width'], 0, 0, $size['height'], $size['width'] + 1);
			imagedestroy($imgg);
		}
	}
	else {
		$dims = imageTextSize($fontsize, $angle, $string);

		switch($fontsize) {
			case 5:
				$fontsize = 1;
				break;
			case 6:
				$fontsize = 1;
				break;
			case 7:
				$fontsize = 2;
				break;
			case 8:
				$fontsize = 2;
				break;
			case 9:
				$fontsize = 3;
				break;
			case 10:
				$fontsize = 3;
				break;
			case 11:
				$fontsize = 4;
				break;
			case 12:
				$fontsize = 4;
				break;
			case 13:
				$fontsize = 5;
				break;
			case 14:
				$fontsize = 5;
				break;
			default:
				$fontsize = 2;
				break;
		}

		if ($angle) {
			$x -= $dims['width'];
			$y -= 2;
		}
		else {
			$y -= $dims['height'] - 2;
		}

		if ($angle > 0) {
			return imagestringup($image, $fontsize, $x, $y, $string, $color);
		}
		return imagestring($image, $fontsize, $x, $y, $string, $color);
	}

	return true;
}

function imageTextSize($fontsize, $angle, $string) {
	$gdinfo = gd_info();

	$result = array();

	if ($gdinfo['FreeType Support'] && function_exists('imagettfbbox')) {
		if (preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && $angle != 0) {
			$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
		}
		else {
			$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
		}

		$ar = imagettfbbox($fontsize, $angle, $ttf, $string);

		$result['height'] = abs($ar[1] - $ar[5]);
		$result['width'] = abs($ar[0] - $ar[4]);
		$result['baseline'] = $ar[1];
	}
	else {
		switch($fontsize) {
			case 5:
				$fontsize = 1;
				break;
			case 6:
				$fontsize = 1;
				break;
			case 7:
				$fontsize = 2;
				break;
			case 8:
				$fontsize = 2;
				break;
			case 9:
				$fontsize = 3;
				break;
			case 10:
				$fontsize = 3;
				break;
			case 11:
				$fontsize = 4;
				break;
			case 12:
				$fontsize = 4;
				break;
			case 13:
				$fontsize = 5;
				break;
			case 14:
				$fontsize = 5;
				break;
			default:
				$fontsize = 2;
				break;
		}

		if ($angle) {
			$result['width'] = imagefontheight($fontsize);
			$result['height'] = imagefontwidth($fontsize) * zbx_strlen($string);
		}
		else {
			$result['height'] = imagefontheight($fontsize);
			$result['width'] = imagefontwidth($fontsize) * zbx_strlen($string);
		}
		$result['baseline'] = 0;
	}

	return $result;
}

function dashedLine($image, $x1, $y1, $x2, $y2, $color) {
	// style for dashed lines
	if (!is_array($color)) {
		$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
	}
	else {
		$style = $color;
	}

	imagesetstyle($image, $style);
	imageline($image, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
}

function dashedRectangle($image, $x1, $y1, $x2, $y2, $color) {
	dashedLine($image, $x1, $y1, $x1, $y2, $color);
	dashedLine($image, $x1, $y2, $x2, $y2, $color);
	dashedLine($image, $x2, $y2, $x2, $y1, $color);
	dashedLine($image, $x2, $y1, $x1, $y1, $color);
}

function find_period_start($periods, $time) {
	$date = getdate($time);
	$wday = $date['wday'] == 0 ? 7 : $date['wday'];
	$curr = $date['hours'] * 100 + $date['minutes'];

	if (isset($periods[$wday])) {
		$next_h = -1;
		$next_m = -1;
		foreach ($periods[$wday] as $period) {
			$per_start = $period['start_h'] * 100 + $period['start_m'];
			if ($per_start > $curr) {
				if (($next_h == -1 && $next_m == -1) || ($per_start < ($next_h * 100 + $next_m))) {
					$next_h = $period['start_h'];
					$next_m = $period['start_m'];
				}
				continue;
			}

			$per_end = $period['end_h'] * 100 + $period['end_m'];
			if ($per_end <= $curr) {
				continue;
			}
			return $time;
		}

		if ($next_h >= 0 && $next_m >= 0) {
			return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);
		}
	}

	for ($days = 1; $days < 7 ; ++$days) {
		$new_wday = ($wday + $days - 1) % 7 + 1;
		if (isset($periods[$new_wday ])) {
			$next_h = -1;
			$next_m = -1;

			foreach ($periods[$new_wday] as $period) {
				$per_start = $period['start_h'] * 100 + $period['start_m'];

				if (($next_h == -1 && $next_m == -1) || ($per_start < ($next_h * 100 + $next_m))) {
					$next_h = $period['start_h'];
					$next_m = $period['start_m'];
				}
			}

			if ($next_h >= 0 && $next_m >= 0) {
				return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'] + $days, $date['year']);
			}
		}
	}
	return -1;
}

function find_period_end($periods, $time, $max_time) {
	$date = getdate($time);
	$wday = $date['wday'] == 0 ? 7 : $date['wday'];
	$curr = $date['hours'] * 100 + $date['minutes'];

	if (isset($periods[$wday])) {
		$next_h = -1;
		$next_m = -1;

		foreach ($periods[$wday] as $period) {
			$per_start = $period['start_h'] * 100 + $period['start_m'];
			$per_end = $period['end_h'] * 100 + $period['end_m'];
			if ($per_start > $curr) {
				continue;
			}
			if ($per_end < $curr) {
				continue;
			}

			if (($next_h == -1 && $next_m == -1) || ($per_end > ($next_h * 100 + $next_m))) {
				$next_h = $period['end_h'];
				$next_m = $period['end_m'];
			}
		}

		if ($next_h >= 0 && $next_m >= 0) {
			$new_time = mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);

			if ($new_time == $time) {
				return $time;
			}
			if ($new_time > $max_time) {
				return $max_time;
			}

			$next_time = find_period_end($periods, $new_time, $max_time);
			if ($next_time < 0) {
				return $new_time;
			}
			else {
				return $next_time;
			}
		}
	}

	return -1;
}
