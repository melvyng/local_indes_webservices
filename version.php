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
 * Based on the work of Jerome Mouneyrac (package local_wstemplate)
 */

$plugin->version  = 2020052501;
$plugin->requires = 2010112400;  // Requires this Moodle version - at least 2.0
$plugin->component = 'local_indes_webservices'; // Full name of the plugin (used for diagnostics)
$plugin->cron     = 0;
$plugin->release = '3.1 (Build: 2016101000)';
$plugin->maturity = MATURITY_STABLE;
