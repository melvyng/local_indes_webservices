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

$functions = array(
		'indes_webservices_sync_registrations' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'sync_registrations',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Return the list of new registrations since the last synchronization process',
			'type'        => 'read',
		),
		'indes_webservices_sync_outputs' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'sync_outputs',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Return the list of new outputs since the last synchronization process',
			'type'        => 'read',
		),
		'indes_webservices_sync_total_registrations' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'sync_total_registrations',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'It retrieves the total number of participants of those courses starting today.',
			'type'        => 'read',
		),
		'indes_webservices_enrol_student' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'enrol_student',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'It enrols students by its email and the course id',
			'type'        => 'write',
		),
		'indes_webservices_unenrol_student' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'unenrol_student',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'It unenrols students by its email and the course id',
			'type'        => 'write',
		),
		'indes_webservices_sync_participants_profile_updated' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'sync_participants_profile_updated',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Return the list of participants who had their profile changed since the last synchronization process',
			'type'        => 'read',
		),
		'indes_webservices_sync_participants_dropout' => array(
			'classname'	  => 'indes_webservices',
			'methodname'  => 'sync_participants_dropout',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Return the list of participants who were droped out from course',
			'type'		  => 'read',
		),
		'indes_webservices_sync_facilitators' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'sync_facilitators',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Return the list of facilitators assigned to a course since the last synchronization process',
			'type'        => 'read',
		),
		'limesurvey_mark_as_completed' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'limesurvey_mark_as_completed',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Mark a LimeSurvey activity as completed for a specific participant',
			'type'        => 'write',
		),
		'indes_webservices_get_grade_participant' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'get_grade_participant',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Get the final course grade for a single user in a course',
			'type'        => 'read',
		),
        'indes_webservices_get_grades_course' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_grades_course',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the final course grade(s) for a single course for one or more users',
            'type'        => 'read',
        ),
        'indes_webservices_get_gradable_activities_course' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_gradable_activities_course',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the final course grade(s) and gradable activities for a single course for one or more users',
            'type'        => 'read',
        ),
        'indes_webservices_get_grades_participant_category' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_grades_participant_category',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the enrolled & dropout participants with their grade from a category (courses)',
            'type'        => 'read',
        ),
        'indes_webservices_get_enrollment_participants_category' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_enrollment_participants_category',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the enrollment participants within a course or category',
            'type'        => 'read',
        ),				
		'indes_webservices_validate_certificate' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'validate_certificate',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Verify if the code given is a valid certificate',
			'type'        => 'read',
		),
        'indes_webservices_get_categories' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_categories',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the ID & category names from the site',
            'type'        => 'read',
        ),
        'indes_webservices_get_questionnaire_course' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_questionnaire_course',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the questionnaire(s), question and answers for a single course',
            'type'        => 'read',
        ),
        'indes_webservices_get_sm_participants' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_sm_participants',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the enrolled & completed participants from a date (courses) for SurveyMonkey',
            'type'        => 'read',
        ),
        'indes_webservices_get_sm_participants_by_course' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_sm_participants_by_course',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the enrolled & completed participants from a course for SurveyMonkey',
            'type'        => 'read',
        ),
        'indes_webservices_get_courses_with_offering' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_courses_with_offering',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get list of courses with Item or Offering numbers',
            'type'        => 'read',
        ),
        'indes_webservices_get_level2_info_by_course' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_level2_info_by_course',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get the questions, choices & responses from participants from surveys level 2',
            'type'        => 'read',
        ),
		'surveymonkey_mark_as_completed' => array(
			'classname'   => 'indes_webservices',
			'methodname'  => 'surveymonkey_mark_as_completed',
			'classpath'   => 'local/indes_webservices/externallib.php',
			'description' => 'Mark a SurveyMonkey activity as completed for a specific participant',
			'type'        => 'write',
		),
        'indes_webservices_get_sm_surveys_by_courses' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_sm_surveys_by_courses',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get list of surveymonkey surveys by courses',
            'type'        => 'read',
        ),
        'indes_webservices_get_sm_users_completed' => array(
            'classname'   => 'indes_webservices',
            'methodname'  => 'get_sm_users_completed',
            'classpath'   => 'local/indes_webservices/externallib.php',
            'description' => 'Get list of users that already completed surveymonkey surveys by each course',
            'type'        => 'read',
        )		
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
	'INDES Web Services' => array(
		'functions' => array ('indes_webservices_sync_registrations', 'indes_webservices_sync_outputs', 'indes_webservices_sync_total_registrations', 'indes_webservices_enrol_student', 'indes_webservices_unenrol_student', 'indes_webservices_sync_participants_profile_updated','indes_webservices_sync_participants_dropout', 'indes_webservices_sync_facilitators',  'limesurvey_mark_as_completed', 'indes_webservices_get_grade_participant', 'indes_webservices_get_grades_course', 'indes_webservices_get_gradable_activities_course', 'indes_webservices_get_grades_participant_category', 'indes_webservices_get_enrollment_participants_category', 'indes_webservices_validate_certificate','indes_webservices_get_categories','indes_webservices_get_questionnaire_course','indes_webservices_get_sm_participants','indes_webservices_get_sm_participants_by_course','indes_webservices_get_courses_with_offering','surveymonkey_mark_as_completed','indes_webservices_get_sm_surveys_by_courses','indes_webservices_get_sm_users_completed'),
		'restrictedusers' => 0,
		'enabled'=>1,
	)
);
