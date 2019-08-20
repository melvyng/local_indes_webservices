<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * INDES Web services external functions and service definitions.
 * @package   indes_webservices
 * @copyright 2012 Inter-American Development Bank (http://www.iadb.org) - 2019 OpenRanger S. A. de C.V.
 * @author    Maiquel Sampaio de Melo - Melvyn Gomez (melvyng@openranger.com)
 * 
 */

defined('MOODLE_INTERNAL') || die;

if (is_siteadmin()) {
    $settings = new admin_settingpage('local_indes_webservices', get_string('pluginname', 'local_indes_webservices'), 'moodle/site:config');
    $ADMIN->add('localplugins', $settings);

    $name = 'local_indes_webservices/categories';
    $title = get_string('indes_webservices_categories', 'local_indes_webservices');
    $description = get_string('indes_webservices_categories_definition', 'local_indes_webservices');
    $default = '60';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $settings->add($setting);
}