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
require_once($CFG->libdir . "/externallib.php");

/**
 * Constants
 */
// Categories that must have their courses synchronized: PAST, ONGOING, UPCOMING VIRTUAL COURSE, INT
define('COURSE_CATEGORIES', "2, 4, 29");

// Average number of hours of learning activities per week (section)
define('HOURS_PER_WEEK', 10);

// Student role
define('ROLE_STUDENT', 5);
// Drop out role
define('ROLE_DROPOUT', 16);
// Roles of facilitators
define('ROLE_COORDINATOR',	12);
define('ROLE_TUTOR', 		3);

// User Profile fields 
define('PROFILE_FIELD_INSTITUTION_NAME', 7 );
define('PROFILE_FIELD_INSTITUTION_TYPE', 20);
define('PROFILE_FIELD_INSTITUTION_CITY', 9);
define('PROFILE_FIELD_INSTITUTION_COUNTRY', 10);
define('PROFILE_FIELD_INSTITUTION_POSITION', 17);
define('PROFILE_FIELD_GENDER', 15);
define('PROFILE_FIELD_HIGHEST_DEGREE', 6);
define('PROFILE_COUNTRY', 'US');

// Paypal Enrollment Request constants
define('ENROLLMENT_PAYPAL_REGULAR', "REGULAR");
define('ENROLLMENT_PAYPAL_SCHOLARSHIP', "SCHOLARSHIP");

// User Enrolment Status
define('USER_ENROL_STATUS_ENABLED', 0);
define('USER_ENROL_STATUS_DISABLED', 1);

class indes_webservices extends external_api {

   /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_registrations_parameters() {
        return new external_function_parameters(
                array('lastsynctime' => new external_value(PARAM_INT, 'Time when the last synchronization was successfully run.'))
        );
    }

    /**
     * Returns the list of participants that must be registered in KNL System as participants
     * @return array List of registrations created since the last synchronization
     */
    public static function sync_registrations($lastsynctime) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::sync_registrations_parameters(),
                array('lastsynctime' => $lastsynctime));

		$sql = "
		    SELECT
                c.id as courseId,
                u.id as userId,
                lower(u.email) AS email,
                u.firstname,
                u.lastname,
                title.data as title,
                inst_name.data as institution_name,
                inst_type.data as institution_type,
                inst_city.data as institution_city,
                inst_country.data as institution_country,
                gender.data as gender,
                a.timemodified

				FROM
					({$CFG->prefix}role_assignments a,
					{$CFG->prefix}course c,
					{$CFG->prefix}user u,
					{$CFG->prefix}context cx)

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_name
				ON
					inst_name.userid = u.id
					AND inst_name.fieldid = " . PROFILE_FIELD_INSTITUTION_NAME . " 

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_type
				ON
					inst_type.userid = u.id
					AND inst_type.fieldid = " . PROFILE_FIELD_INSTITUTION_TYPE . " 

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_city
				ON
					inst_city.userid = u.id
					AND inst_city.fieldid = " . PROFILE_FIELD_INSTITUTION_CITY . "

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_country
				ON
					inst_country.userid = u.id
					AND inst_country.fieldid = " . PROFILE_FIELD_INSTITUTION_COUNTRY . "

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data title
				ON
					title.userid = u.id
					AND title.fieldid = " . PROFILE_FIELD_INSTITUTION_POSITION . "

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data gender
				ON
					gender.userid = u.id
					AND gender.fieldid = " . PROFILE_FIELD_GENDER . "

				WHERE
					a.timemodified > " . $lastsynctime . "
					AND a.roleid = " . ROLE_STUDENT . "

					AND u.id = a.userid
					AND u.deleted = 0

					AND a.contextid = cx.id

					AND cx.instanceid = c.id
					AND (c.idnumber <> '' and c.idnumber IS NOT NULL) -- KNA ID
					AND c.category IN (" . COURSE_CATEGORIES . ")
				ORDER BY
					a.timemodified ASC";

		$enrolledusers = $DB->get_recordset_sql($sql);

        $users = array();
        foreach ($enrolledusers as $user) {
            $newUser = array();

            $newUser['courseId'] = $user->courseid;
            $newUser['userId'] = $user->userid;
            $newUser['email'] = $user->email;
            $newUser['firstname'] = $user->firstname;
            $newUser['lastname'] = $user->lastname;
            $newUser['title'] = $user->title;
            $newUser['institution_name'] = $user->institution_name;
            $newUser['institution_city'] = $user->institution_city;
            $newUser['institution_country'] = $user->institution_country;
            $newUser['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $user->gender);
            $newUser['timemodified'] = $user->timemodified;
            $newUser['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $user->institution_type);

            $users[] = $newUser;
        }

        $enrolledusers->close();

        return $users;
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function sync_registrations_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseId'				=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'userId'				=> new external_value(PARAM_NUMBER, 'ID of the user'),
                    'email'					=> new external_value(PARAM_RAW, 'Email of the user'),
                    'firstname'				=> new external_value(PARAM_RAW, 'firstname of the user'),
                    'lastname'				=> new external_value(PARAM_RAW, 'lastname of the user'),
                    'title'					=> new external_value(PARAM_RAW, 'title of the user'),
                    'institution_name'		=> new external_value(PARAM_RAW, 'institution_name of the user'),
                    'institution_type'		=> new external_value(PARAM_RAW, 'institution_type of the user'),
                    'institution_city'		=> new external_value(PARAM_RAW, 'institution_city of the user'),
                    'institution_country'	=> new external_value(PARAM_RAW, 'institution_country of the user'),
                    'gender'    			=> new external_value(PARAM_RAW, 'gender of the user'),
                    'timemodified'			=> new external_value(PARAM_RAW, 'timemodified of the user')
                )
            )
        );
    }


   /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_outputs_parameters() {
        return new external_function_parameters(
                array('lastsynctime' => new external_value(PARAM_INT, 'Time when the last synchronization was successfully run.'))
        );
    }


    /**
     * Returns the list of courses that must be registered in KNL System as outputs
     * @return array List of outputs updated since the last synchronization
     */
    public static function sync_outputs($lastsynctime) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::sync_outputs_parameters(),
                array('lastsynctime' => $lastsynctime));

		$sql = "
			SELECT 
				c.id,
				c.idnumber,
				c.fullname,
				c.shortname,
				c.summary,
				0 as cost,
				c.timecreated,
				c.startdate,
				c.startdate as enrolstartdate,
				c.startdate as enrolenddate,
				c.timemodified,
				LEFT(lang, 2) as language,
				(
					SELECT 
						count(*)
					FROM
						{$CFG->prefix}role_assignments a,
						{$CFG->prefix}user u,
						{$CFG->prefix}context cx
					WHERE
						a.roleid = " . ROLE_STUDENT . "
						AND a.contextid = cx.id
						AND cx.instanceid = c.id
						AND u.id = a.userid
				) as total_registrations,
				(
					IFNULL(
							(
								SELECT 
									enr.deadline
								FROM
									{$CFG->prefix}enrol erl,
									{$CFG->prefix}enrol_request enr
								WHERE
									c.id = erl.courseid
									AND erl.id = enr.enrolid
							)
						,0)
				) as deadline,
				(
					SELECT 
						value
					FROM 
						{$CFG->prefix}course_format_options cfo
					WHERE
						c.id = cfo.courseid
						AND cfo.name = 'numsections'
				) as numsections

			 FROM 
				{$CFG->prefix}course c
			 WHERE 
				c.timemodified > " . $lastsynctime . "
				AND trim(c.idnumber) <> '' 
				AND c.idnumber IS NOT NULL
				AND c.category IN (" . COURSE_CATEGORIES . ")
			 ORDER BY 
				timemodified ASC";

		$courses = $DB->get_recordset_sql($sql);

        $outputs = array();
        foreach ($courses as $course) {
            $output = array();

            // Business Rule: Deadline is provided in the Enrollment Request instance.
            // Exception: Courses without Enrollment Request have the deadline as one day before the start date 
            if($course->deadline > 0) {
                $deadline = $course->deadline;
            } else {
                $deadline = strtotime("-1 days", $course->startdate);
            }

            $output['courseId'] = $course->id;
            $output['knaId'] = preg_replace("/[^0-9]/","", $course->idnumber); //only numbers
            $output['name'] = $course->fullname;
            $output['shortname'] = $course->shortname;
            $output['comments'] = $course->summary;
            $output['registration_fee'] = $course->cost;
            $output['created_date'] = $course->timecreated;
            $output['start_date'] = $course->startdate;
            $output['end_date'] = strtotime("+" . $course->numsections . " weeks", $course->startdate);
            $output['published_date'] = $course->enrolstartdate;
            $output['deadline_date'] = $deadline;
            $output['duration'] = $course->numsections * HOURS_PER_WEEK;
            $output['last_modified_timestamp'] = $course->timemodified;
            $output['language'] = $course->language;
            $output['total_registrations'] = $course->total_registrations;

            $outputs[] = $output;
        }

        $courses->close();

        return $outputs;
    }


    /**
     * Returns description of method result value
     * @return external_multiple_structure
     */
    public static function sync_outputs_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseId'    				=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'knaId'    					=> new external_value(PARAM_NUMBER, 'ID of the K&L Activity (KNA)'),
					'name'    					=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'shortname'    				=> new external_value(PARAM_RAW, 'Short Name / Code of the course'),
					'comments'    				=> new external_value(PARAM_RAW, 'Comments of the course'),
					'registration_fee'			=> new external_value(PARAM_RAW, 'Registration fee of the course'),
					'created_date'				=> new external_value(PARAM_RAW, 'Date when the course was created'),
					'start_date'				=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'					=> new external_value(PARAM_RAW, 'Date when the course will end'),
					'published_date'			=> new external_value(PARAM_RAW, 'Date when the course was published'),
					'deadline_date'				=> new external_value(PARAM_RAW, 'Deadline for participant registration'),
					'duration'					=> new external_value(PARAM_NUMBER, 'Duration of the course'),
					'last_modified_timestamp'	=> new external_value(PARAM_RAW, 'Time when the course was updated by the last time'),
					'language'					=> new external_value(PARAM_RAW, 'Language of the course'),
					'total_registrations'		=> new external_value(PARAM_NUMBER, 'Total number of active registrations in this course'),
				)
			)
		);
    }


   /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_total_registrations_parameters() {
        return new external_function_parameters(
                array()
        );
    }


    /**
     * It retrieves the total number of participants of those courses starting today.  
	 * This information is used to update the information required for the Cost Per Capita.
     * @return array List of outputs with their total of registrations
     */
    public static function sync_total_registrations() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
			SELECT 
				c.id,
				c.idnumber, 
				c.fullname, 
				c.summary, 
				0 as cost,
				c.timecreated, 
				c.startdate,  
				c.startdate as enrolstartdate, 
				c.startdate as enrolenddate, 
				c.timemodified, 
				LEFT(lang, 2) as language,
				(
					SELECT 
						count(*)
					FROM            
						{$CFG->prefix}role_assignments a,
						{$CFG->prefix}user u,
						{$CFG->prefix}context cx
					WHERE
						a.roleid = " . ROLE_STUDENT . "
						AND a.contextid = cx.id
						AND cx.instanceid = c.id
						AND u.id = a.userid
				) as total_registrations,
				(
					IFNULL(
							(
								SELECT 
									enr.deadline
								FROM
									{$CFG->prefix}enrol erl,
									{$CFG->prefix}enrol_request enr
								WHERE
									c.id = erl.courseid
									AND erl.id = enr.enrolid
							)
						,0)
				) as deadline,
				(
					SELECT 
						value
					FROM 
						{$CFG->prefix}course_format_options cfo
					WHERE
						c.id = cfo.courseid
						AND cfo.name = 'numsections'
				) as numsections

			 FROM 
				{$CFG->prefix}course c
			 WHERE 
				trim(c.idnumber) <> '' 
				AND c.idnumber IS NOT NULL
				AND c.category IN (" . COURSE_CATEGORIES . ")
				AND DATEDIFF(current_date(), date_format(from_unixtime(c.startdate),'%Y%m%d')) = 7
			 ORDER BY 
				timemodified ASC";

		$courses = $DB->get_recordset_sql($sql);

        $outputs = array();

        foreach ($courses as $course) {

            $output = array();

            // Business Rule: Deadline is provided in the Enrollment Request instance.
            // Exception: Courses without Enrollment Request have the deadline as one day before the start date 
            if($course->deadline > 0) {
                $deadline = $course->deadline;
            } else {
                $deadline = strtotime("-1 days", $course->startdate);
            }

            $output['courseId'] = $course->id;
            $output['knaId'] = preg_replace("/[^0-9]/","", $course->idnumber); //only numbers
            $output['name'] = $course->fullname;
            $output['comments'] = $course->summary;
            $output['registration_fee'] = $course->cost;
            $output['created_date'] = $course->timecreated;
            $output['start_date'] = $course->startdate;
            $output['end_date'] = strtotime("+" . $course->numsections . " weeks", $course->startdate);
            $output['published_date'] = $course->enrolstartdate;
            $output['deadline_date'] = $deadline;
            $output['duration'] = $course->numsections * HOURS_PER_WEEK;
            $output['last_modified_timestamp'] = $course->timemodified;
            $output['language'] = $course->language;
            $output['total_registrations'] = $course->total_registrations;

            $outputs[] = $output;
        }

        $courses->close();

        return $outputs;
    }


    /**
     * Returns description of method result value
     * @return external_multiple_structure
     */
    public static function sync_total_registrations_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseId'    				=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'knaId'    					=> new external_value(PARAM_NUMBER, 'ID of the K&L Activity (KNA)'),
					'name'    					=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'comments'    				=> new external_value(PARAM_RAW, 'Comments of the course'),
					'registration_fee'    		=> new external_value(PARAM_RAW, 'Registration fee of the course'),
					'created_date'    			=> new external_value(PARAM_RAW, 'Date when the course was created'),
					'start_date'    			=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'    				=> new external_value(PARAM_RAW, 'Date when the course will end'),
					'published_date'    		=> new external_value(PARAM_RAW, 'Date when the course was published'),
					'deadline_date'    			=> new external_value(PARAM_RAW, 'Deadline for participant registration'),
					'duration'    				=> new external_value(PARAM_NUMBER, 'Duration of the course'),
					'last_modified_timestamp'	=> new external_value(PARAM_RAW, 'Time when the course was updated by the last time'),
					'language'    				=> new external_value(PARAM_RAW, 'Language of the course'),
					'total_registrations'    	=> new external_value(PARAM_NUMBER, 'ID of the course'),
				)
			)
		);
    }


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function enrol_student_parameters() {
		return new external_function_parameters(
			array(
					'email' 	=> new external_value(PARAM_RAW, 'Student email'),
					'courseid' 	=> new external_value(PARAM_INT, 'ID of Moodle course')
				)
		);
	}


    /**
     * Enrolment of a student in a course using the manual enrollment plug-in
     * Function throw an exception at the first error encountered.
     * @param string $email Student email
	 * @param int $courseid Course ID
     * @return null
     */
    public static function enrol_student($email, $courseid) {
        global $DB, $CFG;

        require_once($CFG->dirroot.'/enrol/manual/externallib.php');

        $student_role_shortname = 'student';

        if (!$role = $DB->get_record('role', array('shortname' => $student_role_shortname))) {
            throw new moodle_exception('indes_webservices_no_student_role', 'indes_webservices', '', $student_role_shortname);
        }

        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            throw new moodle_exception('indes_webservices_course_not_found', 'indes_webservices', '', $courseid);
        }

        if (!$user = $DB->get_record('user', array('email' => $email))) {
            throw new moodle_exception('indes_webservices_student_email_not_found', 'indes_webservices', '', $email);
        }

        // Manual enrollment method accepts an array of request
        $enrollments = array();

        $enrollment = array();
		$enrollment['roleid'] = $role->id; 
		$enrollment['userid'] = $user->id;
		$enrollment['courseid'] = $course->id;

		array_push($enrollments, $enrollment);

		// Enroll student using the manual enrollment plug-in
		enrol_manual_external::enrol_users($enrollments);
	}


	/**
	 * Returns description of method result value
	 * @return null
	 */
	public static function enrol_student_returns() {
		return null;
	}


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function unenrol_student_parameters() {
		return new external_function_parameters(
			array(
					'email' 	=> new external_value(PARAM_RAW, 'Student email'),
					'courseid' 	=> new external_value(PARAM_INT, 'ID of Moodle course')
				)
		);
	}


    /**
     * Unenrol a student from all enrollment methods of a course
     * Function throw an exception at the first error encountered.
     * @param string $email Student email
	 * @param int $courseid Course ID
     * @return null
     */
    public static function unenrol_student($email, $courseid) {
        global $DB, $CFG, $PAGE;

        require_once($CFG->dirroot.'/enrol/locallib.php');

        $student_role_shortname = 'student';

        if (!$role = $DB->get_record('role', array('shortname' => $student_role_shortname))) {
            throw new moodle_exception('indes_webservices_no_student_role', 'indes_webservices', '', $student_role_shortname);
        }

        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            throw new moodle_exception('indes_webservices_course_not_found', 'indes_webservices', '', $courseid);
        }

        if (!$user = $DB->get_record('user', array('email' => $email))) {
            throw new moodle_exception('indes_webservices_student_email_not_found', 'indes_webservices', '', $email);
        }

        $enrollment_manager = new course_enrolment_manager($PAGE, $course);

        $user_enrollments = $enrollment_manager->get_user_enrolments($user->id);

        // Unenroll student from each enrollment method of this course
        foreach ($user_enrollments as $ue) {
            $enrollment_manager->unenrol_user($ue);
        }
	}


	/**
	 * Returns description of method result value
	 * @return null
	 */
	public static function unenrol_student_returns() {
		return null;
	}


    /**
     * Returns List of participants who were droped out from course
     * @return external_function_parameters
     */
     public static function sync_participants_dropout_parameters() {
        return new external_function_parameters(
                array('lastsynctime' => new external_value(PARAM_INT, 'Time when the last synchronization was successfully run.'))
        );
    }


    /**
     * Returns the list of participants who where droped out from course since last synchronization
     * @return array List of participants droped out since the last synchronization
     */
	public static function sync_participants_dropout($lastsynctime) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::sync_participants_dropout_parameters(),
                array('lastsynctime' => $lastsynctime));

		$sql = "
			SELECT
				c.id as courseId,
				u.id as userId,
				u.firstname,
				u.lastname,
				lower(u.email) AS email,
				a.timemodified
			FROM
				({$CFG->prefix}role_assignments a,
				{$CFG->prefix}course c,
				{$CFG->prefix}user u,
				{$CFG->prefix}context ct,
				{$CFG->prefix}user_enrolments uen,
				{$CFG->prefix}enrol enr)
			WHERE
				uen.enrolid = enr.id
				AND enr.courseid = c.id
				AND ct.instanceid = c.id
				AND a.contextid = ct.id
				AND u.id = a.userid
				AND uen.userid = u.id
				AND uen.status = " . USER_ENROL_STATUS_DISABLED . "
				AND a.timemodified > " . $lastsynctime . "
				AND a.roleid = " . ROLE_DROPOUT . "
				AND ct.contextlevel = ". CONTEXT_COURSE;

		$usersDropedout = $DB->get_recordset_sql($sql);

		$users = array();
        foreach ($usersDropedout as $user) {
            $newUser = array();
            $newUser['courseId'] = $user->courseid;
            $newUser['userId'] = $user->userid;
            $newUser['email'] = $user->email;
            $newUser['firstname'] = $user->firstname;
            $newUser['lastname'] = $user->lastname;
            $newUser['timemodified'] = $user->timemodified;

            $users[] = $newUser;
        }

        $usersDropedout->close();

        return $users;

	}


	 /**
     * Returns Result structure for the sync_participants_dropout method
     * @return external_description
     */
    public static function sync_participants_dropout_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseId'    	=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'userId'    	=> new external_value(PARAM_NUMBER, 'ID of the user'),
					'email'    		=> new external_value(PARAM_RAW, 'Email of the user'),
					'firstname'		=> new external_value(PARAM_RAW, 'firstname of the user'),
					'lastname'    	=> new external_value(PARAM_RAW, 'lastname of the user'),
					'timemodified'	=> new external_value(PARAM_RAW, 'timemodified of the user')
				)
			)
		);
    }


   /**
     * Returns List of participants who had their profile updated
     * @return List of participants who had their profile updated
     */
    public static function sync_participants_profile_updated_parameters() {
        return new external_function_parameters(
                array('lastsynctime' => new external_value(PARAM_INT, 'Time when the last synchronization was successfully run.'))
        );
    }


    /**
     * Returns the list of participants that had their profile updated since last synchronization
     * @return array List of registrations created since the last synchronization
     */
    public static function sync_participants_profile_updated($lastsynctime) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::sync_participants_profile_updated_parameters(),
                array('lastsynctime' => $lastsynctime));

		$sql = "
			SELECT
                c.id as courseId,
                u.id as userId,
                lower(u.email) as email,
                u.firstname,
                u.lastname,
                u.timemodified

				FROM
					({$CFG->prefix}role_assignments a,
					{$CFG->prefix}course c,
					{$CFG->prefix}user u,
					{$CFG->prefix}context cx)

				WHERE
					u.timemodified > " . $lastsynctime . "
					AND a.roleid = " . ROLE_STUDENT . "
					AND u.id = a.userid
					AND u.deleted = 0
					AND a.contextid = cx.id
					AND cx.instanceid = c.id
					AND (c.idnumber <> '' and c.idnumber IS NOT NULL) -- KNA ID
					AND c.category IN (" . COURSE_CATEGORIES . ")
				ORDER BY
					u.timemodified ASC";

		$usersProfileUpdated = $DB->get_recordset_sql($sql);

        $users = array();
        foreach ($usersProfileUpdated as $user) {
            $newUser = array();
            $newUser['courseId'] = $user->courseid;
            $newUser['userId'] = $user->userid;
            $newUser['email'] = $user->email;
            $newUser['firstname'] = $user->firstname;
            $newUser['lastname'] = $user->lastname;
            $newUser['timemodified'] = $user->timemodified;

            $users[] = $newUser;
        }

        $usersProfileUpdated->close();

        return $users;
    }


    /**
     * Returns Result structure for the sync_participants_profile_updated method
     * @return external_description
     */
    public static function sync_participants_profile_updated_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseId'    	=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'userId'    	=> new external_value(PARAM_NUMBER, 'ID of the user'),
					'email'    		=> new external_value(PARAM_RAW, 'Email of the user'),
					'firstname'    	=> new external_value(PARAM_RAW, 'firstname of the user'),
					'lastname'    	=> new external_value(PARAM_RAW, 'lastname of the user'),
					'timemodified'	=> new external_value(PARAM_RAW, 'timemodified of the user')
				)
			)
		);
    }


	/**
     * Returns List of parameters for method sync_facilitators
     * @return external_function_parameters
     */
    public static function sync_facilitators_parameters() {
        return new external_function_parameters(
                array('lastsynctime' => new external_value(PARAM_INT, 'Time when the last synchronization was successfully run.'))
        );
    }


    /**
     * Returns the list of facilitators that were assigned to a course since last synchronization
     * @return array List of facilitators that were assigned to a course since last synchronization
     */
    public static function sync_facilitators($lastsynctime) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::sync_facilitators_parameters(),
                array('lastsynctime' => $lastsynctime));

		$sql = "
			SELECT
             	c.id as courseid,
             	u.id as userid,
             	u.firstname,
             	u.lastname,
             	lower(u.email) AS email,
             	r.shortname as role,
             	title.data as title,
                inst_name.data as institution_name,
                inst_type.data as institution_type,
                inst_city.data as institution_city,
                inst_country.data as institution_country,
                gender.data as gender,
				a.timemodified
			FROM
				(
				{$CFG->prefix}role r,
				{$CFG->prefix}role_assignments a,
				{$CFG->prefix}course c,
				{$CFG->prefix}user u,
				{$CFG->prefix}context ct,
				{$CFG->prefix}user_enrolments uen,
				{$CFG->prefix}enrol enr)

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_name
				ON
					inst_name.userid = u.id
					AND inst_name.fieldid = " . PROFILE_FIELD_INSTITUTION_NAME . " 

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_type
				ON
					inst_type.userid = u.id
					AND inst_type.fieldid = " . PROFILE_FIELD_INSTITUTION_TYPE . " 

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_city
				ON
					inst_city.userid = u.id
					AND inst_city.fieldid = " . PROFILE_FIELD_INSTITUTION_CITY . "
				
				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data inst_country
				ON
					inst_country.userid = u.id
					AND inst_country.fieldid = " . PROFILE_FIELD_INSTITUTION_COUNTRY . "

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data title
				ON
					title.userid = u.id
					AND title.fieldid = " . PROFILE_FIELD_INSTITUTION_POSITION . "

				LEFT OUTER JOIN
					{$CFG->prefix}user_info_data gender
				ON
					gender.userid = u.id
					AND gender.fieldid = " . PROFILE_FIELD_GENDER . "

			WHERE
				uen.enrolid = enr.id
				AND enr.courseid = c.id
				AND uen.userid = u.id
				AND u.id = a.userid
				AND a.roleid = r.id
				AND a.contextid = ct.id
				AND ct.instanceid = c.id
				AND c.category IN (" . COURSE_CATEGORIES . ")
				AND uen.status = " . USER_ENROL_STATUS_ENABLED . "
				AND a.roleid IN (" . ROLE_COORDINATOR . ", " . ROLE_TUTOR . ")
				AND ct.contextlevel = ". CONTEXT_COURSE . "
				AND a.timemodified > " . $lastsynctime . "
			 ORDER BY
				a.timemodified ASC
		";

		$facilitators = $DB->get_recordset_sql($sql);

        $users = array();
        foreach ($facilitators as $user) {
            $newUser = array();
            $newUser['courseId'] = $user->courseid;
            $newUser['userId'] = $user->userid;
            $newUser['email'] = $user->email;
            $newUser['firstname'] = $user->firstname;
            $newUser['lastname'] = $user->lastname;
            $newUser['role'] = $user->role;
            $newUser['title'] = $user->title;
            $newUser['institution_name'] = $user->institution_name;
            $newUser['institution_city'] = $user->institution_city;
            $newUser['institution_country'] = $user->institution_country;
            $newUser['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $user->institution_type);
            $newUser['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $user->gender);
            $newUser['timemodified'] = $user->timemodified;

            $users[] = $newUser;
        }

        $facilitators->close();

        return $users;
    }


    /**
     * Returns structure for method sync_facilitators
     * @return structure
     */
    public static function sync_facilitators_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseId'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'userId'    			=> new external_value(PARAM_NUMBER, 'Facilitator\'s ID'),
                    'email'    				=> new external_value(PARAM_RAW, 'Facilitator\'s Email'),
                    'firstname'    			=> new external_value(PARAM_RAW, 'Facilitator\'s First Name'),
                    'lastname'    			=> new external_value(PARAM_RAW, 'Facilitator\'s Last Name'),
                    'role'					=> new external_value(PARAM_RAW, 'Facilitator\'s Role'),
                    'title'					=> new external_value(PARAM_RAW, 'Facilitator\'s Title'),
                    'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
                    'institution_city'		=> new external_value(PARAM_RAW, 'City of the institution that the Facilitator belongs to'),
                    'institution_country'	=> new external_value(PARAM_RAW, 'Country of the institution that the Facilitator belongs to'),
                    'institution_type'		=> new external_value(PARAM_RAW, 'Type of the institution that the Facilitator belongs to'),
                    'gender'    			=> new external_value(PARAM_RAW, 'Facilitator\'s gender'),					
                    'timemodified'    		=> new external_value(PARAM_RAW, 'Time when the facilitator was assigned to the course')
                )
            )
        );
    }


	/**
	 * Definition of parameters for limesurvey_mark_as_completed function
	 * @return external_function_parameters - Definition of parameters
	 */
	public static function limesurvey_mark_as_completed_parameters() {
		return new external_function_parameters(
			array(
					'surveyid' 	=> new external_value(PARAM_INT, 'ID of the survey'),
					'userid' 	=> new external_value(PARAM_RAW, 'ID of the user')
				)
		);
	}


	/**
	 * Mark a LimeSurvey activity as completed for a specific participant
	 * @param string $surveyid ID of the survey
	 * @param int $userid ID of the user
	 * @return null
	 */
	public static function limesurvey_mark_as_completed($surveyid, $userid) {
		global $DB, $CFG, $PAGE;

		require_once($CFG->dirroot.'/enrol/locallib.php');
		require_once($CFG->libdir.'/completionlib.php');

		if (!$limesurvey = $DB->get_record('limesurvey', array('surveyid' => $surveyid))) {
			throw new moodle_exception('limesurvey_not_found', 'indes_webservices', '', $surveyid);
		}

		if (!$user = $DB->get_record('user', array('id' => $userid))) {
			throw new moodle_exception('limesurvey_user_not_found', 'indes_webservices', '', $userid);
		}

		$course = $DB->get_record('course', array('id'=> $limesurvey->course), '*', MUST_EXIST);

		$completion_info = new completion_info($course);

		$params = array('userid'=>$userid, 'surveyid'=>$limesurvey->id);

        $cm = get_coursemodule_from_instance('limesurvey', $limesurvey->id, 0, false, MUST_EXIST);

		if($completion_info->is_enabled($cm) && !$DB->record_exists('limesurvey_tracking', $params)){

			$record = new stdClass();
			$record->surveyid		= $limesurvey->id;
			$record->userid			= $userid;
			$record->completed		= 1;
			$record->timemodified	= time();

			$DB->insert_record('limesurvey_tracking', $record, false);

			$completion = $completion_info->update_state($cm, COMPLETION_COMPLETE, $user->id);
		}
	}


	/**
	 * The method limesurvey_mark_as_completed does not return anything
	 * @return null
	 */
	public static function limesurvey_mark_as_completed_returns() {
		return null;
	}


	/**
	 * Returns the aggregated or calculated course grade for a single user
	 * @return external_function_parameters
	 */
	public static function get_grade_participant_parameters() {
		return new external_function_parameters(
			array(
					'email' 	=> new external_value(PARAM_RAW, 'Student email'),
					'courseid' 	=> new external_value(PARAM_INT, 'ID of Moodle course')
				)
		);
	}


	/**
	* Returns the aggregated or calculated course grade for a single user for one course
	* Function throw an exception at the first error encountered.
	* @param string $email Student email
	* @param int $courseid Course ID
	* @return null
	*/
    public static function get_grade_participant($email, $courseid) {
        global $CFG, $USER, $DB;

		require_once($CFG->dirroot . "/grade/lib.php");
		require_once($CFG->dirroot . "/grade/querylib.php");

		$userid = $DB->get_field('user', 'id', array('email' => $email));

		$result_grade = grade_get_course_grade($userid, $courseid);

		$final_grade = $result_grade->grade;

		$user_grade = array();
		$grade = array();
		$grade['email'] = $email;
		$grade['userId'] = $userid;
		$grade['courseId'] = $courseid;
		$grade['final_grade'] = $final_grade;

		$user_grade[] = $grade;

		return $user_grade;
	}


	/**
	* Returns Result structure for the get_grade_participant method
	* @return external_description
	*/
    public static function get_grade_participant_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'email'    		=> new external_value(PARAM_RAW, 'Email of the user'),
                    'userId'    	=> new external_value(PARAM_NUMBER, 'ID of the user'),
                    'courseId'    	=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'final_grade'   => new external_value(PARAM_NUMBER, 'final grade of the user')
                )
            )
        );
    }


    /**
     * Returns the aggregated or calculated course grade(s) for a single course for one or more users
     * @return external_function_parameters
     */
    public static function get_grades_course_parameters() {
        return new external_function_parameters(
            array(
                    'courseid'  => new external_value(PARAM_INT, 'ID of Moodle course')
                )
        );
    }


    /**
    * Returns the aggregated or calculated course grade for a single user for one course
    * Function throw an exception at the first error encountered.
    * @param string $email Student email
    * @param int $courseid Course ID
    * @return null
    */
    public static function get_grades_course($courseid) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . "/grade/lib.php");
        require_once($CFG->dirroot . "/grade/querylib.php");

        $users_enrolled = get_enrolled_users(context_course::instance($courseid));

        $users_in_course = array();
        foreach($users_enrolled as $users) {
            $users_in_course[] = $users->id;
        }

        $result_grades = grade_get_course_grades($courseid,$users_in_course);

        $grades = array();
        foreach($result_grades->grades as $key => $value) {
            $newUser = array();
            $newUser['userId'] = $key;
            $newUser['final_grade'] = $value->grade;

            $grades[] = $newUser;
        }

        return $grades;
    }


    /**
    * Returns Result structure for the get_grades_course method
    * @return external_description
    */
    public static function get_grades_course_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userId'        => new external_value(PARAM_NUMBER, 'ID of the user'),
                    'final_grade'   => new external_value(PARAM_NUMBER, 'final grade of the user')
                )
            )
        );
    }


    /**
     * Returns the aggregated or calculated course grade(s) for a single course for one or more users
     * @return external_function_parameters
     */
    public static function get_gradable_activities_course_parameters() {
        return new external_function_parameters(
            array(
                    'courseid'  => new external_value(PARAM_INT, 'ID of Moodle course')
                )
        );
    }


    /**
    * Returns the aggregated or calculated course grade for a single user for one course
    * Function throw an exception at the first error encountered.
    * @param string $email Student email
    * @param int $courseid Course ID
    * @return null
    */
    public static function get_gradable_activities_course($courseid) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . "/grade/lib.php");
        require_once($CFG->dirroot . "/grade/querylib.php");

        $users_enrolled = get_enrolled_users(context_course::instance($courseid));

        $users_in_course = array();
        foreach($users_enrolled as $users) {
			$users_in_course[$users->id]['courseId'] = $courseid;
			$users_in_course[$users->id]['userId'] = $users->id;
			$users_in_course[$users->id]['email'] = $users->email;

			$result_grades = grade_get_course_grades($courseid,$users->id);

			foreach($result_grades->grades as $key => $value) {
				$users_in_course[$users->id]['final_grade'] = $value->grade;
			}

			$sql = "
				SELECT 
					course as Course_Id,
					userid as User_Id,
					email,
					scormid,
					grademethod,
					completionscorerequired,
					value
				FROM
					(
					SELECT
						t.id as trackid,
						u.id as userid,
						CONCAT(firstname, ' ', lastname) as fullname,
						email,
						course,
						t.scormid,
						s.grademethod,
						s.completionscorerequired,
						name,
						attempt,
						value,
						(SELECT
							FROM_UNIXTIME(t2.timemodified)
						FROM
							{$CFG->prefix}scorm_scoes_track t2
						WHERE
							t2.userid = t.userid
							AND t2.scoid = t.scoid
							AND t2.element = 'x.start.time'
							AND t2.scormid = t.scormid
							AND t2.attempt = t.attempt) as start_date_scorm,
						FROM_UNIXTIME(t.timemodified) as last_access_scorm
					FROM
						{$CFG->prefix}scorm_scoes_track t,
						{$CFG->prefix}scorm s, {$CFG->prefix}user u
					WHERE
						s.id = t.scormid
						AND t.userid = u.id
						AND t.element = 'cmi.core.score.raw'
						AND s.course IN ($courseid)
						AND t.userid IN ($users->id)

					UNION

					SELECT
						t.id as trackid,
						u.id as userid,
						CONCAT(firstname, ' ', lastname) as fullname,
						email,
						course,
						t.scormid,
						s.grademethod,
						s.completionscorerequired,
						name,
						attempt,
						CASE value
							WHEN 'completed' THEN 'Completed'
							WHEN 'passed' THEN 'Completed'
							ELSE 'Incomplete'
						END as value,
						(SELECT 
							FROM_UNIXTIME(t2.timemodified)
						FROM
							{$CFG->prefix}scorm_scoes_track t2
						WHERE
							t2.userid = t.userid
							AND t2.scoid = t.scoid
							AND t2.element = 'x.start.time'
							AND t2.scormid = t.scormid
							AND t2.attempt = t.attempt) as start_date_scorm,
						FROM_UNIXTIME(t.timemodified) as last_access_scorm
					FROM
						{$CFG->prefix}scorm_scoes_track t,
						{$CFG->prefix}scorm s, {$CFG->prefix}user u
					WHERE
						s.id = t.scormid
						AND t.userid = u.id
						AND t.element = 'cmi.core.lesson_status'
						AND s.course IN ($courseid)
						AND t.userid IN ($users->id)
				) AS table_a
			GROUP BY trackid, userid, fullname, email, name, attempt
			ORDER BY email
			";

			$scorm_result = $DB->get_recordset_sql($sql);

			$users_in_course[$users->id]['scorms'] = array();
			foreach ($scorm_result as $h) {
				$scorms_count++;
				$users_in_course[$users->id]['scorms'][$scorms_count] = array();
				$users_in_course[$users->id]['scorms'][$scorms_count]['scormid'] = $h->scormid;
				$users_in_course[$users->id]['scorms'][$scorms_count]['grademethod'] = $h->grademethod;
				$users_in_course[$users->id]['scorms'][$scorms_count]['completionscorerequired'] = $h->completionscorerequired;
				$users_in_course[$users->id]['scorms'][$scorms_count]['value'] = $h->value;
			}
			$scorms_count = 0;
        }

        return $users_in_course;
    }


    /**
    * Returns Result structure for the get_grades_course method
    * @return external_description
    */
    public static function get_gradable_activities_course_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseId'    	=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'userId'    	=> new external_value(PARAM_NUMBER, 'ID of the user'),
					'email'    		=> new external_value(PARAM_RAW, 'Email of the user'),
					'scorms' 	=> new external_multiple_structure(
										new external_single_structure(
											array(
												'scormid'  					=> new external_value(PARAM_NUMBER, 'The name of the preference'),
												'grademethod' 				=> new external_value(PARAM_NUMBER, 'The value of the preference'),
												'completionscorerequired'  	=> new external_value(PARAM_RAW, 'The name of the preference'),
												'value'  					=> new external_value(PARAM_RAW, 'The name of the preference')
											)
										), 'User preferences', VALUE_OPTIONAL),
                    'final_grade'   => new external_value(PARAM_NUMBER, 'final grade of the user')
                )
            )
        );
    }


    /**
     * Get the enrolled & dropout participants with their grade from a category (courses)
     * @return external_function_parameters
     */
    public static function get_grades_participant_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma')
                )
        );
    }


    /**
    * Get the enrolled & dropout participants with their grade from a category (courses)
    * @param int $categoryid Category ID
    */
    public static function get_grades_participant_category($categoryid) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
				SELECT
					c.id as course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.lang,
					c.startdate,
					c.enddate,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					mul.timeaccess as UnixUser_LastAccess,
					mul.timeaccess as User_LastAccess,
					CASE
						WHEN mul.timeaccess < c.startdate THEN 'I'
						WHEN mul.timeaccess >= c.startdate THEN 'A'
					END as User_Status,
					ra.roleid,
					ra.timemodified,
					COALESCE(ROUND(gg.finalgrade,2),0) as finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM 
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN {$CFG->prefix}user_lastaccess mul ON ct.instanceid = mul.courseid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND mul.userid = ra.userid
					AND cca.id IN ($categoryid)

				UNION

				SELECT
					c.id as course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.lang,
					c.startdate,
					c.enddate,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					'' as UnixUser_LastAccess,
					'' as User_LastAccess,
					'N' as User_Status,
					ra.roleid,
					ra.timemodified,
					COALESCE(ROUND(gg.finalgrade,2),0) as finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM 
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
				LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
			WHERE
				ct.contextlevel = 50
				AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
				AND ra.userid not in (select mul.userid from {$CFG->prefix}user_lastaccess mul where mul.courseid = c.id and mul.userid = ra.userid)
				AND cca.id IN ($categoryid)
			";
			/* This section was part of the SELECT, Change made 3/1/2023
					u.firstname AS firstname, 
					u.lastname AS lastname, 
					u.lastip,
					(
						SELECT 
							cfo.value
						FROM 
							{$CFG->prefix}course_format_options cfo
						WHERE
							c.id = cfo.courseid
							AND cfo.name = 'numsections'
					) as numsections,
			*/
			//AND (cca.id = $categoryid OR cca.parent = $categoryid OR cca.path like '%/$categoryid/%')


		$participants = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($participants as $participant) {

            //Calculo nuevo para mostrar el porcentaje de un usuario en un curso 2/14/2023
            /*$course_info = get_course($participant->course_id);

            $completion = new \completion_info($course_info);

            $percentage = core_completion\progress::get_course_progress_percentage($course_info, $participant->user_id);
            if (!is_null($percentage)) {
                $percentage = round($percentage, 2);
            }*/
            //Calculo nuevo para mostrar el porcentaje de un usuario en un curso 2/14/2023

            $registration = array();

            $fecha_inicio = date_create();
            $fecha_inicio = date_timestamp_set($fecha_inicio, $participant->startdate);

            $fecha_fin = date_create();
            $fecha_fin = date_timestamp_set($fecha_fin, $participant->enddate);

            $diff_fecha_inicio_fin = date_diff($fecha_inicio,$fecha_fin);

            $idnumber = explode("|", $participant->idnumber);

            $registration['courseid'] = $participant->course_id;
            $registration['categoryid'] = $participant->category;
            $registration['course_type'] = $participant->course_type;
            $registration['item'] = $idnumber[0];
            $registration['offering'] = $idnumber[1];
            $registration['shortname'] = $participant->shortname;
            $registration['name'] = $participant->fullname;
            $registration['lang'] = $participant->lang;
            $registration['start_date'] = date('Y-m-d H:i:s', $participant->startdate);
            $registration['end_date'] = date('Y-m-d H:i:s', $participant->enddate);
            //$registration['calculated_end_date'] = date('Y-m-d H:i:s', strtotime("+" . $participant->numsections . " weeks", $participant->startdate));
            $registration['credithours'] = $participant->credithours;
            //$registration['calculated_credithours'] = ($diff_fecha_inicio_fin->format("%R%a")/7)*10;
            //$registration['numsections'] = $participant->numsections;
            $registration['userid'] = $participant->user_id;
            //$registration['firstname'] = $participant->firstname;
            //$registration['lastname'] = $participant->lastname;
            $registration['email'] = $participant->email;
            $registration['unixuser_lastaccess'] = $participant->unixuser_lastaccess;
            $registration['user_lastaccess'] = ($participant->unixuser_lastaccess != '' ? date('Y-m-d H:i:s', $participant->unixuser_lastaccess) : '');
            $registration['user_status'] = $participant->user_status;
            //$registration['percentage'] = $percentage;
            //$registration['lastip'] = $participant->lastip;
            $registration['roleid'] = $participant->roleid;
            $registration['timemodified'] = date('Y-m-d H:i:s', $participant->timemodified);
            $registration['finalgrade'] = $participant->finalgrade;
            $registration['country'] = filterProfileFields(PROFILE_COUNTRY, $participant->country);
            $registration['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $participant->gender);
            $registration['degree'] = filterProfileFields(PROFILE_FIELD_HIGHEST_DEGREE, $participant->highest_degree);
            $registration['institution_name'] = $participant->institution;
            $registration['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $participant->institution_type);

            $registrations[] = $registration;
        }

        $participants->close();

        return $registrations;
    }


    /**
     * Returns structure for method get_grades_participant_category
     * @return structure
     */
    public static function get_grades_participant_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'categoryid'    		=> new external_value(PARAM_NUMBER, 'ID of the category course'),
					'course_type'    		=> new external_value(PARAM_RAW, 'Type of course'),
					'item'    				=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
					'offering' 				=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'name'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'lang'    				=> new external_value(PARAM_RAW, 'Language of the course'),
					'start_date'			=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'				=> new external_value(PARAM_RAW, 'Moodle date when the course will end'),
					//'calculated_end_date'	=> new external_value(PARAM_RAW, 'Calculated date when the course will end'),
					'credithours'			=> new external_value(PARAM_RAW, 'Moodle credit housrs of the course'),
					//'calculated_credithours'=> new external_value(PARAM_RAW, 'Calculated credit housrs of the course based on the calculated end date'),
					//'numsections'    		=> new external_value(PARAM_NUMBER, 'Course num of sections'),
					'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
					//'firstname'    			=> new external_value(PARAM_RAW, 'User\'s First Name'),
					//'lastname'    			=> new external_value(PARAM_RAW, 'User\'s Last Name'),
					'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
					'unixuser_lastaccess'	=> new external_value(PARAM_RAW, 'Unix date when the user accessed the last time to the course'),
					'user_lastaccess'		=> new external_value(PARAM_RAW, 'Human date when the user accessed the last time to the course'),
					'user_status'  			=> new external_value(PARAM_RAW, 'User Last Access Status'),
					//'percentage'  			=> new external_value(PARAM_NUMBER, 'Participant percentage course completed'),
					//'lastip'    			=> new external_value(PARAM_RAW, 'User\'s Last IP'),
					'roleid'				=> new external_value(PARAM_RAW, 'User\'s role ID'),
					'timemodified'    		=> new external_value(PARAM_RAW, 'Time when the users was enrolled to the course'),
					'finalgrade'			=> new external_value(PARAM_NUMBER, 'Final grade in the course'),
					'country'				=> new external_value(PARAM_RAW, 'Participant\'s Country'),
					'gender'    			=> new external_value(PARAM_RAW, 'User\'s gender'),
					'degree'    			=> new external_value(PARAM_RAW, 'User\'s degree'),
					'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
					'institution_type'		=> new external_value(PARAM_RAW, 'Type of the institution that the Facilitator belongs to')
				)
			)
		);
    }


    /**
     * Get the enrolled & dropout participants (without firstname & lastname) with their grade from a category (courses), including user status
     * @return external_function_parameters
     */
    public static function get_nameless_grades_participant_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma'),
					'startdate'  => new external_value(PARAM_INT, 'Start date of courses to filter')
                )
        );
    }

    /**
    * Get the enrolled & dropout participants (without firstname & lastname) with their grade from a category (courses), including user status
    * @param int $categoryid Category ID
    */
	public static function get_nameless_grades_participant_category($categoryid, $startdate = NULL) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_nameless_grades_participant_category_parameters(),
                array('categoryid' => $categoryid, 'startdate' => $startdate));

		$sql = "
				SELECT
					c.id AS course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.startdate,
					c.enddate,
					c.lang,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					mul.timeaccess AS UnixUser_LastAccess,
					mul.timeaccess AS User_LastAccess,
					CASE
						WHEN mul.timeaccess < c.startdate THEN 'I'
						WHEN mul.timeaccess >= c.startdate THEN 'A'
					END AS User_Status,
					u.lastip,
					ra.roleid,
					ra.timemodified,
					COALESCE(ROUND(gg.finalgrade,2),0) AS finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 16 AND d_inst.userid = u.id) AS dateofbirth,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN {$CFG->prefix}user_lastaccess mul ON ct.instanceid = mul.courseid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND mul.userid = ra.userid
					AND cca.id IN ($categoryid)
					AND c.startdate > " . $startdate . "

				UNION

				SELECT
					c.id AS course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.startdate,
					c.enddate,
					c.lang,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					'' as UnixUser_LastAccess,
					'' as User_LastAccess,
					'N' as User_Status,
					u.lastip,
					ra.roleid,
					ra.timemodified,
					COALESCE(ROUND(gg.finalgrade,2),0) as finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 16 AND d_inst.userid = u.id) AS dateofbirth,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM 
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND ra.userid not in (select mul.userid from {$CFG->prefix}user_lastaccess mul where mul.courseid = c.id and mul.userid = ra.userid)
					AND cca.id IN ($categoryid)
					AND c.startdate > " . $startdate;

        $participants = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($participants as $participant) {

            $registration = array();

            $fecha_inicio = date_create();
            $fecha_inicio = date_timestamp_set($fecha_inicio, $participant->startdate);

            $fecha_fin = date_create();
            $fecha_fin = date_timestamp_set($fecha_fin, $participant->enddate);

            $diff_fecha_inicio_fin = date_diff($fecha_inicio,$fecha_fin);

            $idnumber = explode("|", $participant->idnumber);

            $registration['courseid'] = $participant->course_id;
            $registration['categoryid'] = $participant->category;
            $registration['course_type'] = $participant->course_type;
            $registration['item'] = $idnumber[0];
            $registration['offering'] = $idnumber[1];
            $registration['shortname'] = $participant->shortname;
            $registration['name'] = $participant->fullname;
            $registration['start_date'] = date('Y-m-d H:i:s', $participant->startdate);
            $registration['end_date'] = date('Y-m-d H:i:s', $participant->enddate);
            $registration['lang'] = $participant->lang;
            //$registration['calculated_end_date'] = date('Y-m-d H:i:s', strtotime("+" . $participant->numsections . " weeks", $participant->startdate));
            $registration['credithours'] = $participant->credithours;
            //$registration['calculated_credithours'] = ($diff_fecha_inicio_fin->format("%R%a")/7)*10;
            //$registration['numsections'] = $participant->numsections;
            $registration['userid'] = $participant->user_id;
            //$registration['firstname'] = $participant->firstname;
            //$registration['lastname'] = $participant->lastname;
            $registration['email'] = $participant->email;
            $registration['unixuser_lastaccess'] = $participant->unixuser_lastaccess;
            $registration['user_lastaccess'] = ($participant->unixuser_lastaccess != '' ? date('Y-m-d H:i:s', $participant->unixuser_lastaccess) : '');
            $registration['user_status'] = $participant->user_status;
            $registration['lastip'] = $participant->lastip;
            $registration['roleid'] = $participant->roleid;
            $registration['timemodified'] = date('Y-m-d H:i:s', $participant->timemodified);
            $registration['finalgrade'] = $participant->finalgrade;
            $registration['country'] = filterProfileFields(PROFILE_COUNTRY, $participant->country);
            $registration['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $participant->gender);
            $registration['dateofbirth'] = $participant->dateofbirth;
            $registration['degree'] = filterProfileFields(PROFILE_FIELD_HIGHEST_DEGREE, $participant->highest_degree);
            $registration['institution_name'] = $participant->institution;
            $registration['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $participant->institution_type);

            $registrations[] = $registration;
        }

        $participants->close();

        return $registrations;
    }


    /**
     * Returns structure for method get_nameless_grades_participant_category
     * @return structure
     */
    public static function get_nameless_grades_participant_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'categoryid'    		=> new external_value(PARAM_NUMBER, 'ID of the category ourse'),
					'course_type'    		=> new external_value(PARAM_RAW, 'Type of course'),
					'item'    				=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
					'offering' 				=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'name'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'start_date'			=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'				=> new external_value(PARAM_RAW, 'Moodle date when the course will end'),
					'lang'					=> new external_value(PARAM_RAW, 'Language of the course'),
					'credithours'			=> new external_value(PARAM_RAW, 'Moodle credit housrs of the course'),
					'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
					'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
					'unixuser_lastaccess'	=> new external_value(PARAM_RAW, 'Unix date when the user accessed the last time to the course'),
					'user_lastaccess'		=> new external_value(PARAM_RAW, 'Human date when the user accessed the last time to the course'),
					'user_status'  			=> new external_value(PARAM_RAW, 'User Last Access Status'),
					'lastip'    			=> new external_value(PARAM_RAW, 'User\'s Last IP'),
					'roleid'				=> new external_value(PARAM_RAW, 'User\'s role ID'),
					'timemodified'    		=> new external_value(PARAM_RAW, 'Time when the users was enrolled to the course'),
					'finalgrade'			=> new external_value(PARAM_NUMBER, 'Final grade in the course'),
					'country'				=> new external_value(PARAM_RAW, 'Participant\'s Country'),
					'gender'    			=> new external_value(PARAM_RAW, 'User\'s gender'),
					'dateofbirth'    		=> new external_value(PARAM_RAW, 'User\'s dateofbirth'),
					'degree'    			=> new external_value(PARAM_RAW, 'User\'s degree'),
					'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
					'institution_type'		=> new external_value(PARAM_RAW, 'Type of the institution that the Facilitator belongs to')
				)
			)
		);
    }


    /**
     * Get the enrolled & dropout participants (without firstname & lastname) with their grade from a category (courses), not including user status
     * @return external_function_parameters
     */
    public static function kpi_get_nameless_grades_participant_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma'),
					'startdate'  => new external_value(PARAM_INT, 'Start date of courses to filter')
                )
        );
    }


    /**
    * Get the enrolled & dropout participants (without firstname & lastname) with their grade from a category (courses), not including user status
    * @param int $categoryid Category ID
    */
	public static function kpi_get_nameless_grades_participant_category($categoryid, $startdate = NULL) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::kpi_get_nameless_grades_participant_category_parameters(),
                array('categoryid' => $categoryid, 'startdate' => $startdate));

		$sql = "
				SELECT
					c.id AS course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.startdate,
					c.enddate,
					c.lang,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					u.lastip,
					ra.roleid,
					ra.timemodified,
					(SELECT mcc.timestarted FROM {$CFG->prefix}course_completions mcc WHERE mcc.course = c.id AND mcc.userid = u.id) AS Time_Started,
					(SELECT mcc.timecompleted FROM {$CFG->prefix}course_completions mcc WHERE mcc.course = c.id AND mcc.userid = u.id) AS Time_Completed,
					DATEDIFF((SELECT FROM_UNIXTIME(mcc.timecompleted) FROM {$CFG->prefix}course_completions mcc WHERE mcc.course = c.id AND mcc.userid = u.id), FROM_UNIXTIME(c.startdate)) AS Days_Taking_Course,
					(SELECT DATEDIFF(FROM_UNIXTIME(mcc.timecompleted), FROM_UNIXTIME(mcc.timeenrolled)) FROM {$CFG->prefix}course_completions mcc WHERE mcc.course = c.id AND mcc.userid = u.id) AS Days_Until_Completion,
					COALESCE(ROUND(gg.finalgrade,2),0) AS finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 16 AND d_inst.userid = u.id) AS dateofbirth,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND cca.id IN ($categoryid)
					AND c.startdate > " . $startdate;

        $participants = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($participants as $participant) {
			
            $registration = array();

            $fecha_inicio = date_create();
            $fecha_inicio = date_timestamp_set($fecha_inicio, $participant->startdate);

            $fecha_fin = date_create();
            $fecha_fin = date_timestamp_set($fecha_fin, $participant->enddate);

            $diff_fecha_inicio_fin = date_diff($fecha_inicio,$fecha_fin);

            $idnumber = explode("|", $participant->idnumber);

            $registration['courseid'] = $participant->course_id;
            $registration['categoryid'] = $participant->category;
            $registration['course_type'] = $participant->course_type;
            $registration['item'] = $idnumber[0];
            $registration['offering'] = $idnumber[1];
            $registration['shortname'] = $participant->shortname;
            $registration['name'] = $participant->fullname;
            $registration['start_date'] = date('Y-m-d H:i:s', $participant->startdate);
            $registration['end_date'] = date('Y-m-d H:i:s', $participant->enddate);
            $registration['lang'] = $participant->lang;
            $registration['credithours'] = $participant->credithours;
            $registration['userid'] = $participant->user_id;
            $registration['email'] = $participant->email;
            $registration['lastip'] = $participant->lastip;
            $registration['roleid'] = $participant->roleid;
            $registration['timemodified'] = date('Y-m-d H:i:s', $participant->timemodified);
			$registration['time_started'] = date('Y-m-d H:i:s', $participant->time_started);
			$registration['time_completed'] = date('Y-m-d H:i:s', $participant->time_completed);
            $registration['days_taking_course'] = $participant->days_taking_course;
            $registration['days_until_completion'] = $participant->days_until_completion;
            $registration['finalgrade'] = $participant->finalgrade;
            $registration['country'] = filterProfileFields(PROFILE_COUNTRY, $participant->country);
            $registration['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $participant->gender);
            $registration['dateofbirth'] = $participant->dateofbirth;
            $registration['degree'] = filterProfileFields(PROFILE_FIELD_HIGHEST_DEGREE, $participant->highest_degree);
            $registration['institution_name'] = $participant->institution;
            $registration['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $participant->institution_type);

            $registrations[] = $registration;
        }

        $participants->close();

        return $registrations;
    }
	
    /**
     * Returns structure for method kpi_get_nameless_grades_participant_category
     * @return structure
     */
    public static function kpi_get_nameless_grades_participant_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'categoryid'    		=> new external_value(PARAM_NUMBER, 'ID of the category ourse'),
					'course_type'    		=> new external_value(PARAM_RAW, 'Type of course'),
					'item'    				=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
					'offering' 				=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'name'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'start_date'			=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'				=> new external_value(PARAM_RAW, 'Moodle date when the course will end'),
					'lang'					=> new external_value(PARAM_RAW, 'Language of the course'),
					'credithours'			=> new external_value(PARAM_RAW, 'Moodle credit housrs of the course'),
					'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
					'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
					'lastip'    			=> new external_value(PARAM_RAW, 'User\'s Last IP'),
					'roleid'				=> new external_value(PARAM_RAW, 'User\'s role ID'),
					'timemodified'    		=> new external_value(PARAM_RAW, 'Time when the user was enrolled to the course'),
					'time_started'    		=> new external_value(PARAM_RAW, 'Time when the user started the course'),
					'time_completed'    	=> new external_value(PARAM_RAW, 'Time when the user completed the course'),
					'days_taking_course'	=> new external_value(PARAM_NUMBER, 'Days taking course by the user, is the difference between the course startdate and the time completed'),
					'days_until_completion'	=> new external_value(PARAM_NUMBER, 'Days until completion, is the difference between the time completed and time enrolled'),
					'finalgrade'			=> new external_value(PARAM_NUMBER, 'Final grade in the course'),
					'country'				=> new external_value(PARAM_RAW, 'Participant\'s Country'),
					'gender'    			=> new external_value(PARAM_RAW, 'User\'s gender'),
					'dateofbirth'    		=> new external_value(PARAM_RAW, 'User\'s dateofbirth'),
					'degree'    			=> new external_value(PARAM_RAW, 'User\'s degree'),
					'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
					'institution_type'		=> new external_value(PARAM_RAW, 'Type of the institution that the Facilitator belongs to')
				)
			)
		);
    }
	
	
    /**
     * Get the enrolled & dropout participants IDs, progress and user status
     * @return external_function_parameters
     */
    public static function kpi_basic_grades_participant_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma'),
					'startdate'  => new external_value(PARAM_INT, 'Start date of courses to filter')
                )
        );
    }


    /**
    * Get the enrolled & dropout participants IDs, progress and user status
    * @param int $categoryid Category ID
    */
	public static function kpi_basic_grades_participant_category($categoryid, $startdate = NULL) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::kpi_basic_grades_participant_category_parameters(),
                array('categoryid' => $categoryid, 'startdate' => $startdate));

		$sql = "
				SELECT
					c.id AS course_id,
					u.id AS user_id,
					lower(u.email) AS email,
					mul.timeaccess AS UnixUser_LastAccess,
					mul.timeaccess AS User_LastAccess,
					CASE
						WHEN mul.timeaccess < c.startdate THEN 'I'
						WHEN mul.timeaccess >= c.startdate THEN 'A'
					END AS User_Status
				FROM
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN {$CFG->prefix}user_lastaccess mul ON ct.instanceid = mul.courseid
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND mul.userid = ra.userid
					AND cca.id IN ($categoryid)
					AND c.startdate > " . $startdate . "

				UNION

				SELECT
					c.id AS course_id,
					u.id AS user_id,
					lower(u.email) AS email,
					'' as UnixUser_LastAccess,
					'' as User_LastAccess,
					'N' as User_Status
				FROM 
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND ra.userid not in (select mul.userid from {$CFG->prefix}user_lastaccess mul where mul.courseid = c.id and mul.userid = ra.userid)
					AND cca.id IN ($categoryid)
					AND c.startdate > " . $startdate;

        $participants = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($participants as $participant) {
			
            //Calculo nuevo para mostrar el porcentaje de un usuario en un curso 09/25/2024
            $course_info = get_course($participant->course_id);

            $completion = new \completion_info($course_info);

			// Calculate the percentage of completion
			/*$percentage = core_completion\progress::get_course_progress_percentage($course_info, $participant->user_id);
            if (!is_null($percentage)) {
                $percentage = round($percentage, 2);
            }*/
			
            // Check if completion tracking is enabled for the course
            if ($completion->is_enabled()) {

				// Calculate the percentage of completion
				$percentage = core_completion\progress::get_course_progress_percentage($course_info, $participant->user_id);
				if (!is_null($percentage)) {
					$percentage = round($percentage, 2);
				}

					// Check if the user is enrolled in the course
					$context = context_course::instance($course_info->id);
					try {
						if (is_enrolled($context, $participant->user_id)) {
							// Try to fetch completion status
							try {
								$completion_status_response = core_completion_external::get_course_completion_status($participant->course_id, $participant->user_id);
								$completion_status = $completion_status_response['completionstatus']['completed'] ?? 0; // 1 for completed, 0 for not completed
							} catch (moodle_exception $e) {
								// Handle if no completion criteria are set
								if ($e->errorcode == 'nocriteriaset') {
									$completion_status = 0; // Default to 0 (incomplete) if no criteria are set
								} else {
									throw $e; // Re-throw other exceptions
								}
							}
						} else {
							// User is not enrolled in the course, set defaults
							$percentage = 0; // Or whatever default value you'd like for non-enrolled users
							$completion_status = 0; // Set completion to 0 if the user is not enrolled
						}
					} catch (moodle_exception $enrollmentException) {
						// Catch and handle the usernotenroled exception
						if ($enrollmentException->errorcode == 'usernotenroled') {
							// Handle case when user is not enrolled in this course
							$percentage = 0;
							$completion_status = 0; // Set completion status as incomplete
						} else {
							throw $enrollmentException; // Re-throw any other exceptions
						}
					}
			} else {
				// Completion tracking is not enabled for this course
				$percentage = null; // No progress to report if tracking is not enabled
				$completion_status = 0; // Set completion status as 0 if tracking is not enabled
			}

            //Calculo nuevo para mostrar el porcentaje de un usuario en un curso 09/25/2024

            $registration = array();

            $registration['courseid'] = $participant->course_id;
            $registration['userid'] = $participant->user_id;
            $registration['email'] = $participant->email;
            $registration['unixuser_lastaccess'] = $participant->unixuser_lastaccess;
            $registration['user_lastaccess'] = ($participant->unixuser_lastaccess != '' ? date('Y-m-d H:i:s', $participant->unixuser_lastaccess) : '');
            $registration['user_status'] = $participant->user_status;
            $registration['percentage'] = $percentage;
            $registration['completion_status'] = $completion_status;

            $registrations[] = $registration;
        }

        $participants->close();

        return $registrations;
    }
	
    /**
     * Returns structure for method kpi_basic_grades_participant_category
     * @return structure
     */
    public static function kpi_basic_grades_participant_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
					'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
					'unixuser_lastaccess'	=> new external_value(PARAM_RAW, 'Unix date when the user accessed the last time to the course'),
					'user_lastaccess'		=> new external_value(PARAM_RAW, 'Human date when the user accessed the last time to the course'),
					'user_status'  			=> new external_value(PARAM_RAW, 'User Last Access Status'),
					'percentage'  			=> new external_value(PARAM_NUMBER, 'Participant percentage course completed'),
					'completion_status'  	=> new external_value(PARAM_RAW, 'User Last Access Status')
				)
			)
		);
    }


    /**
     * Get the enrolled & dropout participants with their grade from a category (courses)
     * @return external_function_parameters
     */
    public static function get_nameless_grades_participants_by_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma'),
					'startdate'  => new external_value(PARAM_INT, 'Start date of courses to filter')
                )
        );
    }

    /**
    * Get the enrolled & dropout participants (without firstname & lastname) with their grade from a category (courses) without user status
    * @param int $categoryid Category ID
    */
    public static function get_nameless_grades_participants_by_category($categoryid, $startdate = NULL) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_nameless_grades_participants_by_category_parameters(),
                array('categoryid' => $categoryid, 'startdate' => $startdate));

		$sql = "
				SELECT
					c.id as course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id AND mcd.fieldid = (SELECT mcf.id FROM {$CFG->prefix}customfield_field mcf WHERE shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.startdate,
					c.enddate,
					c.lang,
					(
						SELECT MAX(cert.printhours)
						FROM {$CFG->prefix}certificate cert
						WHERE cert.course = c.id
					) AS credithours,
					u.id AS user_id,
					lower(u.email) AS email,
					u.lastip,
					ra.roleid,
					ra.timemodified,
					COALESCE(ROUND(gg.finalgrade,2),0) as finalgrade,
					u.country,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) AS gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 16 AND d_inst.userid = u.id) AS dateofbirth,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) AS highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) AS institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 20 AND d_inst.userid = u.id) AS institution_type
				FROM 
					{$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
					LEFT JOIN
					(
						SELECT
							u.id AS userid,
							c.id AS courseid,
							g.finalgrade AS finalgrade
						FROM 
							{$CFG->prefix}user u
						JOIN {$CFG->prefix}grade_grades g ON g.userid = u.id
						JOIN {$CFG->prefix}grade_items gi ON g.itemid =  gi.id
						JOIN {$CFG->prefix}course c ON c.id = gi.courseid
						WHERE 
							gi.itemtype = 'course'
					) gg ON gg.userid = u.id AND gg.courseid = c.id
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					ct.contextlevel = 50
					AND (ra.roleid = " . ROLE_STUDENT . " OR ra.roleid = " . ROLE_DROPOUT . ")
					AND cca.id IN ($categoryid)";

		if(isset($params["startdate"])){
			$sql .= "AND c.startdate > " . $startdate;
		}

		$participants = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($participants as $participant) {

            $registration = array();

            $fecha_inicio = date_create();
            $fecha_inicio = date_timestamp_set($fecha_inicio, $participant->startdate);

            $fecha_fin = date_create();
            $fecha_fin = date_timestamp_set($fecha_fin, $participant->enddate);

            $diff_fecha_inicio_fin = date_diff($fecha_inicio,$fecha_fin);

            $idnumber = explode("|", $participant->idnumber);

            $registration['courseid'] = $participant->course_id;
            $registration['categoryid'] = $participant->category;
            $registration['course_type'] = $participant->course_type;
            $registration['item'] = $idnumber[0];
            $registration['offering'] = $idnumber[1];
            $registration['shortname'] = $participant->shortname;
            $registration['name'] = $participant->fullname;
            $registration['start_date'] = date('Y-m-d H:i:s', $participant->startdate);
            $registration['end_date'] = date('Y-m-d H:i:s', $participant->enddate);
            $registration['lang'] = $participant->lang;
            $registration['credithours'] = $participant->credithours;
            $registration['userid'] = $participant->user_id;
            $registration['email'] = $participant->email;
            $registration['unixuser_lastaccess'] = '';
            $registration['user_lastaccess'] = '';
            $registration['user_status'] = '';
            $registration['lastip'] = $participant->lastip;
            $registration['roleid'] = $participant->roleid;
            $registration['timemodified'] = date('Y-m-d H:i:s', $participant->timemodified);
            $registration['finalgrade'] = $participant->finalgrade;
            $registration['country'] = filterProfileFields(PROFILE_COUNTRY, $participant->country);
            $registration['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $participant->gender);
            $registration['dateofbirth'] = $participant->dateofbirth;
            $registration['degree'] = filterProfileFields(PROFILE_FIELD_HIGHEST_DEGREE, $participant->highest_degree);
            $registration['institution_name'] = $participant->institution;
            $registration['institution_type'] = filterProfileFields(PROFILE_FIELD_INSTITUTION_TYPE, $participant->institution_type);

            $registrations[] = $registration;
        }

        $participants->close();

        return $registrations;
    }


    /**
     * Returns structure for method get_nameless_grades_participants_by_category
     * @return structure
     */
    public static function get_nameless_grades_participants_by_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'categoryid'    		=> new external_value(PARAM_NUMBER, 'ID of the category ourse'),
                    'course_type'    		=> new external_value(PARAM_RAW, 'Type of course'),
                    'item'    				=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
                    'offering' 				=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
                    'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
                    'name'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
                    'start_date'			=> new external_value(PARAM_RAW, 'Date when the course will start'),
                    'end_date'				=> new external_value(PARAM_RAW, 'Moodle date when the course will end'),
                    'lang'					=> new external_value(PARAM_RAW, 'Language of the course'),
                    'credithours'			=> new external_value(PARAM_RAW, 'Moodle credit housrs of the course'),
                    'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
                    'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
                    'unixuser_lastaccess'	=> new external_value(PARAM_RAW, 'Unix date when the user accessed the last time to the course'),
                    'user_lastaccess'		=> new external_value(PARAM_RAW, 'Human date when the user accessed the last time to the course'),
                    'user_status'  			=> new external_value(PARAM_RAW, 'User Last Access Status'),
                    'lastip'    			=> new external_value(PARAM_RAW, 'User\'s Last IP'),
                    'roleid'				=> new external_value(PARAM_RAW, 'User\'s role ID'),
                    'timemodified'    		=> new external_value(PARAM_RAW, 'Time when the users was enrolled to the course'),
                    'finalgrade'			=> new external_value(PARAM_NUMBER, 'Final grade in the course'),
                    'country'				=> new external_value(PARAM_RAW, 'Participant\'s Country'),
                    'gender'    			=> new external_value(PARAM_RAW, 'User\'s gender'),
                    'dateofbirth'    		=> new external_value(PARAM_RAW, 'User\'s dateofbirth'),
                    'degree'    			=> new external_value(PARAM_RAW, 'User\'s degree'),
                    'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
                    'institution_type'		=> new external_value(PARAM_RAW, 'Type of the institution that the Facilitator belongs to')
                )
            )
        );
    }

    /**
     * Get the enrollment participants within a course or category
     * @return external_function_parameters
     */
    public static function get_enrollment_participants_category_parameters() {
        return new external_function_parameters(
            array(
                    'categoryid'  => new external_value(PARAM_RAW, 'IDs of Moodle categories, separated by comma')
                )
        );
    }


    /**
    * Get the enrolled & dropout participants with their grade from a category (courses)
    * @param int $categoryid Category ID
    */
    public static function get_enrollment_participants_category($categoryid) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
				SELECT
					c.id as course_id,
					c.category,
					CASE
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 1 THEN 'Tutor'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 2 THEN 'Self-paced'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 3 THEN 'Face to Face'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 4 THEN 'Synchronous-online'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 5 THEN 'Open Educational Resources'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 6 THEN 'Communities'
						WHEN (SELECT mcd.value FROM {$CFG->prefix}customfield_data mcd WHERE mcd.instanceid = c.id and mcd.fieldid = (select mcf.id from {$CFG->prefix}customfield_field mcf where shortname = 'coursetype')) = 7 THEN 'Certifications'	
					END AS course_type,
					c.shortname AS shortname,
					c.fullname AS fullname,
					c.startdate,
					c.enddate,
					u.id AS user_id,
					u.firstname AS firstname, 
					u.lastname AS lastname,
					lower(u.email) AS email,
					u.lastip AS lastip,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 7 AND d_inst.userid = u.id) as institution,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 15 AND d_inst.userid = u.id) as gender,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 17 AND d_inst.userid = u.id) as work_position,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 3 AND d_inst.userid = u.id LIMIT 1) as study_field,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 6 AND d_inst.userid = u.id) as highest_degree,
					(SELECT d_inst.data FROM {$CFG->prefix}user_info_data d_inst WHERE d_inst.fieldid = 10 AND d_inst.userid = u.id) as country_work,	
					er.timesubmitted AS submitted_on,
					CASE er.status
						WHEN 0 THEN 'PENDING'
						WHEN 1 THEN 'ENROLLED'
						WHEN 2 THEN 'PAID'
						WHEN 3 THEN 'NOT SELECTED'
						WHEN 4 THEN 'SELECTED'
						WHEN 5 THEN 'SELECTED_SCHOLARSHIP'
						WHEN 6 THEN 'WAITING_LIST'
						WHEN 7 THEN 'PAYMENT_NOT_RECEIVED'
						WHEN 8 THEN 'EARLY_BIRD'
					END AS enrolment_status
				FROM {$CFG->prefix}course c
					INNER JOIN {$CFG->prefix}enrol en ON c.id = en.courseid
					INNER JOIN {$CFG->prefix}enrol_request e ON en.id = e.enrolid
					INNER JOIN {$CFG->prefix}enrol_request_requests er ON e.id = er.enrolrequestid
					INNER JOIN {$CFG->prefix}user u ON u.id = er.userid
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					cca.id IN ($categoryid)
					";
					//(cca.id = $categoryid OR cca.parent = $categoryid OR cca.path like '%/$categoryid/%')

		$enrollments = $DB->get_recordset_sql($sql);

        $registrations = array();

        foreach ($enrollments as $enrollment) {

            $registration = array();

            $registration['courseid'] = $enrollment->course_id;
            $registration['categoryid'] = $enrollment->category;
            $registration['course_type'] = $enrollment->course_type;
            $registration['shortname'] = $enrollment->shortname;
            $registration['name'] = $enrollment->fullname;
            $registration['start_date'] = date('Y-m-d H:i:s', $enrollment->startdate);
            $registration['end_date'] = date('Y-m-d H:i:s', $enrollment->enddate);
            $registration['userid'] = $enrollment->user_id;
            $registration['firstname'] = $enrollment->firstname;
            $registration['lastname'] = $enrollment->lastname;
            $registration['email'] = $enrollment->email;
            $registration['lastip'] = $enrollment->lastip;
            $registration['institution_name'] = $enrollment->institution;
            $registration['gender'] = filterProfileFields(PROFILE_FIELD_GENDER, $enrollment->gender);
            $registration['work_position'] = $enrollment->work_position;
            $registration['study_field'] = $enrollment->study_field;
            $registration['degree'] = filterProfileFields(PROFILE_FIELD_HIGHEST_DEGREE, $enrollment->highest_degree);
            $registration['institution_country'] = $enrollment->country_work;
            $registration['submitted_on'] = date('Y-m-d H:i:s', $enrollment->submitted_on);
            $registration['enrolment_status'] = $enrollment->enrolment_status;

            $registrations[] = $registration;
        }

        $enrollments->close();

        return $registrations;
    }


    /**
     * Returns structure for method get_enrollment_participants_category
     * @return structure
     */
    public static function get_enrollment_participants_category_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
                    'categoryid'    		=> new external_value(PARAM_NUMBER, 'ID of the category ourse'),
					'course_type'    		=> new external_value(PARAM_RAW, 'Type of course'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'name'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'start_date'			=> new external_value(PARAM_RAW, 'Date when the course will start'),
					'end_date'				=> new external_value(PARAM_RAW, 'Date when the course will end'),
					'userid'    			=> new external_value(PARAM_NUMBER, 'User\'s ID'),
					'firstname'    			=> new external_value(PARAM_RAW, 'User\'s First Name'),
					'lastname'    			=> new external_value(PARAM_RAW, 'User\'s Last Name'),
					'email'    				=> new external_value(PARAM_RAW, 'User\'s Email'),
					'lastip'    			=> new external_value(PARAM_RAW, 'User\'s Last IP '),
					'institution_name'		=> new external_value(PARAM_RAW, 'Name of the institution that the Facilitator belongs to'),
					'gender'    			=> new external_value(PARAM_RAW, 'User\'s gender'),
					'work_position'			=> new external_value(PARAM_RAW, 'User\'s work position'),
					'study_field'			=> new external_value(PARAM_RAW, 'User\'s study field'),
					'degree'    			=> new external_value(PARAM_RAW, 'User\'s degree'),
					'institution_country'	=> new external_value(PARAM_RAW, 'Country of the institution that the Facilitator belongs to'),
					'submitted_on'    		=> new external_value(PARAM_RAW, 'Time when the users was enrolled to the course'),
					'enrolment_status'    	=> new external_value(PARAM_RAW, 'Current enrolment status')
				)
			)
		);
    }


	/**
	 * Definition of parameters for indes_webservices_validate_certificate function
	 * @return external_function_parameters - Definition of parameters
	*/
	public static function validate_certificate_parameters() {
		return new external_function_parameters(
			array(
					'certcode' 	=> new external_value(PARAM_RAW, 'Code of the certificate')
				)
		);
	}


    /**
     * Returns the info of a participant, grade gained in a course
     * @return array List of info to show if a certificate is valid
    */	
	public static function validate_certificate($certcode) {
		global $CFG, $USER, $DB;

		require_once("../../config.php");
		require_once($CFG->dirroot . "/grade/lib.php");
		require_once($CFG->dirroot . "/grade/querylib.php");

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::validate_certificate_parameters(),
                array('certcode' => $certcode));

		$ufields = user_picture::fields('u');
		$sql = "SELECT ci.timecreated AS citimecreated,
     	ci.code, ci.certificateid, ci.userid, $ufields, c.*, u.id AS id, u.*
     	FROM {$CFG->prefix}certificate_issues ci
                           INNER JOIN {$CFG->prefix}user u
                           ON u.id = ci.userid
                           INNER JOIN {$CFG->prefix}certificate c
                           ON c.id = ci.certificateid
                           WHERE ci.code = '" . $certcode . "'";

		$certificates = $DB->get_recordset_sql($sql);

		$outputs = array();

		foreach ($certificates as $certdata) {
			$output = array();
			$output['certificateid'] = $certdata->certificateid;
			$output['certificatecode'] = $certdata->code;
			$output['name'] = fullname($certdata);
			$course = $DB->get_record('course', array('id' => $certdata->course));
			if ($course) {
				$output['course'] = $course->fullname;
			}

			// Date format.
			$dateformat = get_string('strftimedate', 'langconfig');

			// Modify printdate so that date is always printed.
			$certdata->printdate = 1;
			$certrecord = new stdClass();
			$certrecord->timecreated = $certdata->citimecreated;
			$certrecord->code = $certdata->code;
			$certrecord->userid = $certdata->userid;
			$userid = $certrecord->id = $certdata->id;

			// Retrieving grade and date for each certificate.
			$result_grade = grade_get_course_grade($userid, $course->id);
			$final_grade = $result_grade->grade;
			$date = $certrecord->timecreated = $certdata->citimecreated;

			if ($date) {
				$output['date'] = userdate($date, $dateformat);
			}
			if ($course) {
				$output['final_grade'] = $final_grade;
			}

			$outputs[] = $output;
		}

		$certificates->close();

		return $outputs;
	}


	/**
     * Returns structure for method validate_certificate
     * @return structure
    */
    public static function validate_certificate_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'certificateid'    	=> new external_value(PARAM_NUMBER, 'ID of the certificate'),
                    'certificatecode'  	=> new external_value(PARAM_RAW, 'Code of the certificate'),
                    'name'				=> new external_value(PARAM_RAW, 'Name of the user'),
					'course'  			=> new external_value(PARAM_RAW, 'Name of the course'),
					'final_grade'		=> new external_value(PARAM_NUMBER, 'Final grade in the course'),
					'date'				=> new external_value(PARAM_RAW, 'Date when the certificate was gained')
				)
			)
		);
    }


    /**
     * Get the ID & category names from the site
     * @return external_function_parameters
     */
    public static function get_categories_parameters() {
        return new external_function_parameters(
            array(
                )
        );
    }


    /**
    * Get the ID & category names from the site
    * @param eaw $details Y/N
    */
    public static function get_categories() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
				SELECT
					id,
					name,
					description,
					parent,
					depth,
					path,
					visible
				FROM 
					{$CFG->prefix}course_categories
				WHERE visible = 1
					";

		$categories = $DB->get_recordset_sql($sql);

		$categories_info = array();

		foreach ($categories as $category) {

			$category_info = array();

			$category_info['categoryid'] = $category->id;
			$category_info['name'] = $category->name;
			$category_info['description'] = $category->description;
			$category_info['parent'] = $category->parent;
			$category_info['depth'] = $category->depth;
			$category_info['path'] = $category->path;

			$categories_info[] = $category_info;
		}

        $categories->close();

		return $categories_info;
    }


    /**
     * Returns structure for method get_categories
     * @return structure
    */
    public static function get_categories_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'categoryid'    	=> new external_value(PARAM_INT, 'ID of the category'),
                    'name'				=> new external_value(PARAM_RAW, 'Name of the category'),
					'description'		=> new external_value(PARAM_RAW, 'Description of the category'),
					'parent'  			=> new external_value(PARAM_INT, 'Parent of the category'),
					'depth'				=> new external_value(PARAM_INT, 'Depth of the category'),
					'path'				=> new external_value(PARAM_RAW, 'Path of the category')
				)
			)
		);
    }


    /**
     * Returns the questionnaire(s), question and answers for a single course
     * @return external_function_parameters
     */
    public static function get_questionnaire_course_parameters() {
        return new external_function_parameters(
            array(
                    'courseid'  => new external_value(PARAM_INT, 'ID of Moodle course')
                )
        );
    }


    /**
    * Returns the questionnaire(s), question and answers for a single course
    * Function throw an exception at the first error encountered.
    * @param string $email Student email
    * @param int $courseid Course ID
    * @return null
    */
    public static function get_questionnaire_course($courseid) {
        global $CFG, $USER, $DB;

		require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');
		require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

		$sql = "
			SELECT
				qs.id AS questionnaire_id,
				qs.name AS questionnaire_name,
				qs.courseid,
				qques.id AS question_id,
				qques.surveyid,
				qques.name AS question_name,
				qques.type_id,
				qques.position,
				qques.content,
				qques.required
			FROM 
				{$CFG->prefix}questionnaire_survey qs
			INNER JOIN {$CFG->prefix}questionnaire_question qques ON qs.id = qques.surveyid
			WHERE qques.deleted = 'n'
			AND qs.courseid = $courseid
			ORDER BY qs.id, qques.position
			";

		$questionnaires = $DB->get_recordset_sql($sql);

		$questionnaires_in_course = array();

		foreach ($questionnaires as $questionnaire) {
			$questionnaires_info = array();
			$questionnaires_info['courseid'] = $questionnaire->courseid;
			$questionnaires_info['questionnaireid'] = $questionnaire->questionnaire_id;
			$questionnaires_info['questionnairename'] = $questionnaire->questionnaire_name;
			$questionnaires_info['surveyid'] = $questionnaire->survey_id;
			$questionnaires_info['questionid'] = $questionnaire->question_id;
			$questionnaires_info['questionname'] = $questionnaire->question_name;
			$questionnaires_info['typeid'] = $questionnaire->type_id;
			$questionnaires_info['position'] = $questionnaire->position;
			$questionnaires_info['content'] = $questionnaire->content;
			$questionnaires_info['required'] = $questionnaire->required;

			switch($questionnaire->type_id){
				case 1:
					$sql_type = "
						SELECT
							r_bool.id AS r_id,
							r_bool.question_id AS q_id,
							r_bool.choice_id AS value
						FROM 
							{$CFG->prefix}questionnaire_response_bool r_bool
						WHERE r_bool.question_id = $questionnaire->question_id
					";
					break;
				case 2:
				case 3:
				case 10:
					$sql_type = "
						SELECT
							r_text.id AS r_id,
							r_text.question_id AS q_id,
							r_text.response AS rankvalue
						FROM 
							{$CFG->prefix}questionnaire_response_text r_text
						WHERE r_text.question_id = $questionnaire->question_id
					";
					break;
				case 4:
				case 6:
					$sql_type = "
					SELECT
						r_single.id AS r_id,
						r_single.question_id AS q_id,
						q_choice.content AS rankvalue
						FROM 
							{$CFG->prefix}questionnaire_resp_single r_single
							LEFT JOIN {$CFG->prefix}questionnaire_quest_choice q_choice
							ON q_choice.question_id = r_single.question_id
						WHERE r_single.question_id = $questionnaire->question_id AND r_single.choice_id = q_choice.id
					";
					break;
				case 5:
					$sql_type = "
						SELECT
							r_mult.id AS r_id,
							r_mult.question_id AS q_id,
							q_choice.content AS value
						FROM
							{$CFG->prefix}questionnaire_resp_multiple r_mult
							LEFT JOIN {$CFG->prefix}questionnaire_quest_choice q_choice
							ON q_choice.question_id = r_mult.question_id
						WHERE r_mult.question_id = $questionnaire->question_id
						AND r_mult.choice_id = q_choice.id
					";
					break;
				case 8:
					$sql_type = "
						SELECT
							r_rank.id AS r_id,
							r_rank.rankvalue AS q_id,
							q_choice.content AS rankvalue
						FROM
							{$CFG->prefix}questionnaire_response_rank r_rank 
							LEFT JOIN {$CFG->prefix}questionnaire_quest_choice q_choice 
							ON q_choice.question_id = r_rank.question_id 
						WHERE r_rank.question_id = $questionnaire->question_id 
						AND r_rank.choice_id = q_choice.id
					";
					break;
				case 9:
					$sql_type = "
						SELECT
							r_date.id AS r_id,
							r_date.question_id AS q_id,
							r_date.response AS value
						FROM 
							{$CFG->prefix}questionnaire_response_date r_date
						WHERE r_date.question_id = $questionnaire->question_id
					";
					break;
			}

			$questionnaire_result = $DB->get_recordset_sql($sql_type);

			foreach ($questionnaire_result as $q) {

				$questionnaires_count++;
				$questionnaires_info['responses'][$questionnaires_count]['rid'] = $q->r_id;
				$questionnaires_info['responses'][$questionnaires_count]['qid'] = $q->q_id;
				$questionnaires_info['responses'][$questionnaires_count]['value'] = $q->value;
			}
			$questionnaires_count = 0;

			$questionnaires_in_course[] = $questionnaires_info;
		}

        $questionnaires->close();

		return $questionnaires_in_course;
	}


    /**
    * Returns Result structure for the questionnaire(s), question and answers for a single course
    * @return external_description
    */
    public static function get_questionnaire_course_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseid'    			=> new external_value(PARAM_NUMBER, 'Course ID'),
					'questionnaireid'    	=> new external_value(PARAM_NUMBER, 'Questionnaire ID'),
					'questionnairename' 	=> new external_value(PARAM_RAW, 'Questionnaire Name'),
					'surveyid' 		 	  	=> new external_value(PARAM_NUMBER, 'Survey ID'),
					'questionid'   			=> new external_value(PARAM_NUMBER, 'Question ID'),
					'questionname' 			=> new external_value(PARAM_RAW, 'Question name'),
					'typeid' 				=> new external_value(PARAM_NUMBER, 'Type of question'),
					'position' 				=> new external_value(PARAM_NUMBER, 'Order position of the question'),
					'content' 				=> new external_value(PARAM_RAW, 'Content of the question (real question)'),
					'required' 				=> new external_value(PARAM_RAW, 'If is required'),
					'responses' 			=> new external_multiple_structure(
												new external_single_structure(
													array(
														'rid'  		=> new external_value(PARAM_NUMBER, 'Response ID'),
														'qid' 		=> new external_value(PARAM_NUMBER, 'Question ID or rank of the question'),
														'value'  	=> new external_value(PARAM_RAW, 'Value or response')
													)
												), 'User preferences', VALUE_OPTIONAL)
                )
            )
        );
    }


    /**
     * Get the enrolled & completed participants from a date (courses) for SurveyMonkey
     * @return external_function_parameters
     */
    public static function get_sm_participants_parameters() {
        return new external_function_parameters(
            array(
                    'startdate'  => new external_value(PARAM_INT, 'Startdate of the courses, to get all participants completed the courses')
                )
        );
    }


    /**
    * Get the enrolled participants from a date (courses) for SurveyMonkey
    * @param int $startdate Startdate of the course(s)
    */
    public static function get_sm_participants($startdate) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$categories = get_config('local_indes_webservices','categories');

		$sql = "
				SELECT
					c.id as courseid,
					c.category,
					c.idnumber,
					c.shortname AS shortname,
					c.fullname AS fullname
				FROM
					{$CFG->prefix}course c
					INNER JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE
					c.startdate >= " . $startdate . "
					AND (cca.id IN ($categories) OR cca.parent IN ($categories))
				";

		$courses = $DB->get_recordset_sql($sql);

		$participants_in_course = array();

        foreach ($courses as $course) {

			$participant_info = array();

			$idnumber = explode("|", $course->idnumber);

			$participant_info['courseID'] = $course->courseid;
			$participant_info['itemID'] = $idnumber[0];
			$participant_info['offeringID'] = $idnumber[1];
			$participant_info['shortname'] = $course->shortname;
			$participant_info['courseTitle'] = $course->fullname;

			$sql_sm = "
				SELECT 
					d.id,
					d.course, 
					d.name, 
					df.id, 
					df.dataid, 
					df.name, 
					dc.id, 
					dc.content 
				FROM mdl_data d 
				INNER JOIN {$CFG->prefix}data_fields df ON d.id = df.dataid
				INNER JOIN {$CFG->prefix}data_content dc ON df.id = dc.fieldid
				WHERE 
					d.course = $course->courseid
					AND lower(d.name) = 'surveymonkey'
			";

			$sm_info = $DB->get_recordset_sql($sql_sm);

			$ids = array();
			foreach($sm_info as $obj){
				$ids[strtolower($obj->name)] = $obj->content;
			}

			$participant_info['templateName'] = $ids['template'];
			$participant_info['notificationEmail'] = $ids['email'];

			$sql_participants = "
				SELECT
					c.id as courseid,
					u.id AS user_id,
					u.firstname AS firstname,
					u.lastname AS lastname,
					lower(u.email) AS email
				FROM {$CFG->prefix}role_assignments ra 
					JOIN {$CFG->prefix}user u ON u.id = ra.userid
					JOIN {$CFG->prefix}role r ON r.id = ra.roleid
					JOIN {$CFG->prefix}context cxt ON cxt.id = ra.contextid
					JOIN {$CFG->prefix}course c ON c.id = cxt.instanceid
					LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
				WHERE ra.userid = u.id
					AND ra.contextid = cxt.id
					AND cxt.contextlevel = 50
					AND cxt.instanceid = c.id
					AND roleid = " . ROLE_STUDENT . "
					AND c.id = $course->courseid
					AND c.startdate > " . $startdate . "
				ORDER BY 
					c.startdate ASC
					";

			$participants_result = $DB->get_recordset_sql($sql_participants);

			//Inicio metodo,extraer todos los participantes registrados
			$course_info = get_course($course->courseid);

			$completion = new \completion_info($course_info);

			$participant_info['participants'] = array();
			foreach ($participants_result as $participant) {
				$percentage = core_completion\progress::get_course_progress_percentage($course_info, $participant->user_id);
				if (!is_null($percentage)) {
					$percentage = round($percentage, 2);
				}
				$participants_count++;
				$participant_info['participants'][$participants_count]['userID'] = $participant->user_id;
				$participant_info['participants'][$participants_count]['firstName'] = $participant->firstname;
				$participant_info['participants'][$participants_count]['lastName'] = $participant->lastname;
				$participant_info['participants'][$participants_count]['email'] = $participant->email;
				$participant_info['participants'][$participants_count]['percentage'] = $percentage;
			}
			//Fin metodo,extraer todos los participantes registrados
			$participants_count = 0;

			$participants_in_course[] = $participant_info;
        }
        $courses->close();

		return $participants_in_course;
    }


    /**
     * Returns structure for method get_sm_participants
     * @return structure
     */
    public static function get_sm_participants_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseID'    			=> new external_value(PARAM_NUMBER, 'Course ID'),
					'itemID'    			=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
					'offeringID' 			=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'courseTitle' 			=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'templateName' 			=> new external_value(PARAM_RAW, 'Templatet Name for the Survey'),
					'notificationEmail' 	=> new external_value(PARAM_RAW, 'Notification Email'),
					'participants' 			=> new external_multiple_structure(
												new external_single_structure(
													array(
														'firstName' 			=> new external_value(PARAM_RAW, 'Firstname of the participant'),
														'lastName'  			=> new external_value(PARAM_RAW, 'Lastname of the participant'),
														'email'  				=> new external_value(PARAM_RAW, 'Email of the participant'),
														'userID'  				=> new external_value(PARAM_NUMBER, 'UserID of the participant'),
														'percentage'  			=> new external_value(PARAM_NUMBER, 'Participant percentage course completed')
													)
												), 'User fields', VALUE_OPTIONAL)
                )
            )
		);
    }


    /**
     * Get the enrolled & completed participants from a courses for SurveyMonkey
     * @return external_function_parameters
     */
    public static function get_sm_participants_by_course_parameters() {
        return new external_function_parameters(
            array(
                    'offering'  => new external_value(PARAM_RAW, 'ID of Item or Offering')
                )
        );
    }


    /**
    * Get the enrolled & completed participants from a course for SurveyMonkey
    * @param int $offering Item ID or Offering ID of the course
    */
    public static function get_sm_participants_by_course($offering) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$categories = get_config('local_indes_webservices','categories');

		$course = $DB->get_record('course', array('idnumber'=>$offering), '*', MUST_EXIST);

		$participants_in_course = array();

		$participant_info = array();

		$idnumber = explode("|", $course->idnumber);

		$participant_info['courseID'] = $course->id;
		$participant_info['itemID'] = $idnumber[0];
		$participant_info['offeringID'] = $idnumber[1];
		$participant_info['shortname'] = $course->shortname;
		$participant_info['courseTitle'] = $course->fullname;

		/** Old method to get the template Name to be used in SurveyMonkey */
		/*
		$sql_sm = "
			SELECT 
				d.id,
				d.course, 
				d.name, 
				df.id, 
				df.dataid, 
				df.name, 
				dc.id, 
				dc.content 
			FROM mdl_data d 
			INNER JOIN {$CFG->prefix}data_fields df ON d.id = df.dataid
			INNER JOIN {$CFG->prefix}data_content dc ON df.id = dc.fieldid
			WHERE 
				d.course = $course->id
				AND lower(d.name) = 'surveymonkey'
			";

		$sm_info = $DB->get_recordset_sql($sql_sm);

		$ids = array();
		foreach($sm_info as $obj){
			$ids[strtolower($obj->name)] = $obj->content;
		}

		$participant_info['templateName'] = $ids['template'];
		$participant_info['notificationEmail'] = $ids['email'];
		*/
		$participant_info['templateName'] = '';
		$participant_info['notificationEmail'] = '';

		$sql_participants = "
			SELECT
				c.id as courseid,
				u.id AS user_id,
				u.firstname AS firstname,
				u.lastname AS lastname,
				lower(u.email) AS email
			FROM {$CFG->prefix}role_assignments ra 
				JOIN {$CFG->prefix}user u ON u.id = ra.userid
				JOIN {$CFG->prefix}role r ON r.id = ra.roleid
				JOIN {$CFG->prefix}context cxt ON cxt.id = ra.contextid
				JOIN {$CFG->prefix}course c ON c.id = cxt.instanceid
				LEFT JOIN {$CFG->prefix}course_categories cca ON c.category = cca.id
			WHERE ra.userid = u.id
				AND ra.contextid = cxt.id
				AND cxt.contextlevel = 50
				AND cxt.instanceid = c.id
				AND roleid = " . ROLE_STUDENT . "
				AND c.id = $course->id
			ORDER BY 
				u.email ASC
				";

		$participants_result = $DB->get_recordset_sql($sql_participants);

		//Inicio metodo,extraer todos los participantes registrados
		$completion = new \completion_info($course);

		$participant_info['participants'] = array();
		foreach ($participants_result as $participant) {
			$percentage = core_completion\progress::get_course_progress_percentage($course, $participant->user_id);
			if (!is_null($percentage)) {
				$percentage = round($percentage, 2);
			}
			$participants_count++;
			$participant_info['participants'][$participants_count]['userID'] = $participant->user_id;
			$participant_info['participants'][$participants_count]['firstName'] = $participant->firstname;
			$participant_info['participants'][$participants_count]['lastName'] = $participant->lastname;
			$participant_info['participants'][$participants_count]['email'] = $participant->email;
			$participant_info['participants'][$participants_count]['percentage'] = $percentage;
		}
		//Fin metodo,extraer todos los participantes registrados
		$participants_count = 0;

		$participants_in_course[] = $participant_info;

		return $participants_in_course;
    }


    /**
     * Returns structure for method get_sm_participants
     * @return structure
     */
    public static function get_sm_participants_by_course_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseID'    			=> new external_value(PARAM_NUMBER, 'Course ID'),
					'itemID'    			=> new external_value(PARAM_RAW, 'SuccessFactors Item ID Number'),
					'offeringID' 			=> new external_value(PARAM_RAW, 'SuccessFactors Offering ID Number'),
					'shortname'    			=> new external_value(PARAM_RAW, 'Short Name of the course'),
					'courseTitle' 			=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'templateName' 			=> new external_value(PARAM_RAW, 'Templatet Name for the Survey'),
					'notificationEmail' 	=> new external_value(PARAM_RAW, 'Notification Email'),
					'participants' 			=> new external_multiple_structure(
												new external_single_structure(
													array(
														'firstName' 			=> new external_value(PARAM_RAW, 'Firstname of the participant'),
														'lastName'  			=> new external_value(PARAM_RAW, 'Lastname of the participant'),
														'email'  				=> new external_value(PARAM_RAW, 'Email of the participant'),
														'userID'  				=> new external_value(PARAM_NUMBER, 'UserID of the participant'),
														'percentage'  			=> new external_value(PARAM_NUMBER, 'Participant percentage course completed')
													)
												), 'User fields', VALUE_OPTIONAL)
                )
            )
		);
    }


   /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_courses_with_offering_parameters() {
        return new external_function_parameters(
                array()
        );
    }


    /**
     * It retrieves the total of courses with offering in the field id number to create Surveys on SurveyMonkey
     * @return array List of courses
     */
    public static function get_courses_with_offering() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
			SELECT 
				c.id,
				c.idnumber, 
				c.shortname,
				c.fullname,
				c.startdate,
				c.enddate,
				(
					SELECT 
						value
					FROM 
						{$CFG->prefix}course_format_options cfo
					WHERE
						c.id = cfo.courseid
						AND cfo.name = 'numsections'
				) as numsections
			 FROM 
				{$CFG->prefix}course c
			 WHERE 
				c.idnumber LIKE 'ITEM%'
				AND YEAR(from_unixtime(c.startdate)) = YEAR(current_date())
				AND (SELECT c_inst.value FROM mdl_customfield_data c_inst WHERE c_inst.fieldid = 1 AND c_inst.instanceid = c.id) = 1
			 ORDER BY 
				c.id ASC";

		$courses = $DB->get_recordset_sql($sql);

        $outputs = array();

        foreach ($courses as $course) {

            $output = array();

            $output['courseId'] = $course->id;
            $output['offeringId'] = $course->idnumber;
            $output['shortname'] = $course->shortname;
            $output['fullname'] = $course->fullname;
            $output['startdate'] = $course->startdate;
			if ($course->enddate == 0){
				if(is_null($course->numsections)){
					$output['enddate'] = $course->startdate;
				}else{
					$output['enddate'] = (string) strtotime("+" . $course->numsections . " weeks", $course->startdate);
				}
			}else{
				$output['enddate'] = $course->enddate;
			}

			$outputs[] = $output;
        }

        $courses->close();

        return $outputs;
    }


    /**
     * Returns description of method result value
     * @return external_multiple_structure
     */
    public static function get_courses_with_offering_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'courseId'    				=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'offeringId'    			=> new external_value(PARAM_RAW, 'ID of Item or Offering stored in SuccessFactors'),
					'shortname'    				=> new external_value(PARAM_RAW, 'Short Name of the course'),					
					'fullname'    				=> new external_value(PARAM_RAW, 'Full Name of the course'),
					'startdate'    				=> new external_value(PARAM_RAW, 'Start date of the course im timestamp'),
					'enddate'    				=> new external_value(PARAM_RAW, 'End date of the course im timestamp')
				)
			)
		);
    }


	/**
	 * Definition of parameters for surveymonkey_mark_as_completed function
	 * @return external_function_parameters - Definition of parameters
	 */
	public static function surveymonkey_mark_as_completed_parameters() {
		return new external_function_parameters(
			array(
					'surveyid' 	=> new external_value(PARAM_INT, 'ID of the survey'),
					'useremail' => new external_value(PARAM_RAW, 'Email of the user')
				)
		);
	}


	/**
	 * Mark a SurveyMonkey activity as completed for a specific participant
	 * @param string $surveyid ID of the survey
	 * @param int $useremail Email of the user
	 * @return null
	 */
	public static function surveymonkey_mark_as_completed($surveyid, $useremail) {
		global $DB, $CFG, $PAGE;

		require_once($CFG->dirroot.'/enrol/locallib.php');
		require_once($CFG->libdir.'/completionlib.php');

		if (!$surveymonkey = $DB->get_record('surveymonkey', array('surveyid' => $surveyid))) {
			throw new moodle_exception('surveymonkey_not_found', 'indes_webservices', '', $surveyid);
		}

		if (!$user = $DB->get_record('user', array('email' => $useremail))) {
			throw new moodle_exception('surveymonkey_user_not_found', 'indes_webservices', '', $useremail);
		}

        $course = $DB->get_record('course', array('id'=> $surveymonkey->course), '*', MUST_EXIST);

		$completion_info = new completion_info($course);

		$params = array('userid'=>$user->id, 'surveyid'=>$surveymonkey->id);
		//$params = array('useremail'=>$useremail, 'surveyid'=>$surveymonkey->id);

        $cm = get_coursemodule_from_instance('surveymonkey', $surveymonkey->id, 0, false, MUST_EXIST);	

		if($completion_info->is_enabled($cm) && !$DB->record_exists('surveymonkey_tracking', $params)){

			$record = new stdClass();
			$record->surveyid		= $surveymonkey->id;
			$record->userid			= $user->id;
			$record->completed		= 1;
			$record->timemodified	= time();

			$DB->insert_record('surveymonkey_tracking', $record, false);

			$completion = $completion_info->update_state($cm, COMPLETION_COMPLETE, $user->id);
		}
	}


	/**
	 * The method surveymonkey_mark_as_completed does not return anything
	 * @return null
	 */
	public static function surveymonkey_mark_as_completed_returns() {
		return null;
	}


	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_sm_surveys_by_courses_parameters() {
        return new external_function_parameters(
                array()
        );
    }


    /**
     * It retrieves the total of surveys from surveymonkey from the courses
     * @return array List of surveys
     */
    public static function get_sm_surveys_by_courses() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
			SELECT 
				sm.id,
				sm.name,
				sm.course,
				sm.surveyid
			 FROM 
				{$CFG->prefix}surveymonkey sm
			 WHERE 
				sm.surveyid != 0
			 ORDER BY 
				sm.course ASC";

		$courses = $DB->get_recordset_sql($sql);

        $outputs = array();

        foreach ($courses as $course) {

            $output = array();

            $output['smId'] = $course->id;
            $output['smName'] = $course->name;
            $output['smCourse'] = $course->course;
            $output['smSurveyId'] = $course->surveyid;

            $outputs[] = $output;
        }

        $courses->close();

        return $outputs;
    }


    /**
     * Returns description of method result value
     * @return external_multiple_structure
     */
    public static function get_sm_surveys_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'smId'    				=> new external_value(PARAM_NUMBER, 'Field ID from surveymonkey table'),
					'smName'    			=> new external_value(PARAM_RAW, 'Name of the survey'),
					'smCourse'    			=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'smSurveyId'    		=> new external_value(PARAM_NUMBER, 'Survey ID from SurveyMonkey')
				)
			)
		);
    }


	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_sm_users_completed_parameters() {
        return new external_function_parameters(
                array()
        );
    }

    /**
     * It retrieves the total of users that already completed surveymonkey surveys by each course
     * @return array List of participants for each survemonkey
     */
    public static function get_sm_users_completed() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");

		$sql = "
			SELECT
				s.course,
				s.surveyid,
				t.userid,
				lower(u.email) AS email, 
				concat(s.surveyid, '|', lower(u.email)) AS sid_uemail 
			FROM
				{$CFG->prefix}surveymonkey_tracking t
			INNER JOIN
				{$CFG->prefix}surveymonkey s ON t.surveyid = s.id
			INNER JOIN
				{$CFG->prefix}user u on t.userid = u.id
			WHERE 
				s.surveyid != 0
			ORDER BY 
				s.course ASC, u.email";

		$courses = $DB->get_recordset_sql($sql);

        $outputs = array();

        foreach ($courses as $course) {

            $output = array();

            $output['smCourseId'] = $course->course;
            $output['smSurveyId'] = $course->surveyid;
            $output['smUserId'] = $course->userid;
            $output['smEmail'] = $course->email;
            $output['smSidEmail'] = $course->sid_uemail;

            $outputs[] = $output;
        }

        $courses->close();

        return $outputs;
    }


    /**
     * Returns description of method result value
     * @return external_multiple_structure
     */
    public static function get_sm_users_completed_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
					'smCourseId'    		=> new external_value(PARAM_NUMBER, 'ID of the course'),
					'smSurveyId'    		=> new external_value(PARAM_NUMBER, 'Survey ID from SurveyMonkey'),
					'smUserId'    			=> new external_value(PARAM_NUMBER, 'User ID from Moodle'),
					'smEmail'    			=> new external_value(PARAM_RAW, 'User email'),
					'smSidEmail'    		=> new external_value(PARAM_RAW, 'Concatenation of Survey ID plus | plus user email to create a unique string')
				)
			)
		);
    }


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function update_bigbluebuttonbn_recordings_parameters() {
		return new external_function_parameters(
			array(
					'idrecording' 		=> new external_value(PARAM_INT, 'ID of the recording'),
					'courseid' 			=> new external_value(PARAM_INT, 'ID of Moodle course'),
					'bigbluebuttonbnid'	=> new external_value(PARAM_INT, 'ID of the BBB instance'),
					'status' 			=> new external_value(PARAM_INT, 'new status of the recording'),
					'confirmation' 		=> new external_value(PARAM_RAW, 'String Y to update the record')
				)
		);
	}


	/**
	 * Update the status of a specific recording in the table mdl_bigbluebuttonbn_recordings
	 * @param int $idrecording ID of the recording from the table mdl_bigbluebuttonbn_recordings
	 * @param int $courseid ID of the course
	 * @param int $bigbluebuttonbnid ID of the BBB instance, from table mdl_bigbluebuttonbn
	 * @param int $status new status of the recording, possible values: 0, 1, 2, 3, 4 or 5	 
	 * @param string $confirmation value "Y" to Update, if is different the query won't will run
	 * @return null
	 */
	public static function update_bigbluebuttonbn_recordings($idrecording, $courseid, $bigbluebuttonbnid, $status, $confirmation) {
		global $DB, $CFG;

		if($confirmation == "Y"){
			$sql = "UPDATE {$CFG->prefix}bigbluebuttonbn_recordings mbr
					INNER JOIN {$CFG->prefix}bigbluebuttonbn mbb on mbb.id = mbr.bigbluebuttonbnid
					SET status = $status
					WHERE mbr.id = $idrecording AND mbr.courseid = $courseid AND mbr.bigbluebuttonbnid = $bigbluebuttonbnid";
			echo $sql;
			echo "\n";
			$DB->execute($sql);
		}else{
			echo "Record Not Updated";
		}
	}


	/**
	 * The method update_bigbluebuttonbn_recordings does not return anything
	 * @return null
	 */
	public static function update_bigbluebuttonbn_recordings_returns() {
		return null;
	}


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function update_bigbluebuttonbn_recordingids_parameters() {
		return new external_function_parameters(
			array(
					'idrecording' 		=> new external_value(PARAM_INT, 'ID of the recording'),
					'courseid' 			=> new external_value(PARAM_INT, 'ID of Moodle course'),
					'bigbluebuttonbnid'	=> new external_value(PARAM_INT, 'ID of the BBB instance'),
					'recordingid' 		=> new external_value(PARAM_RAW, 'new status of the recording'),
					'confirmation' 		=> new external_value(PARAM_RAW, 'String Y to update the record')
				)
		);
	}


	/**
	 * Update the recordingid of a specific recording in the table mdl_bigbluebuttonbn_recordings
	 * @param int $idrecording ID of the recording from the table mdl_bigbluebuttonbn_recordings
	 * @param int $courseid ID of the course
	 * @param int $bigbluebuttonbnid ID of the BBB instance, from table mdl_bigbluebuttonbn
	 * @param int $recordingid new recordingid of the recording
	 * @param string $confirmation value "Y" to Update, if is different the query won't will run
	 * @return null
	 */
	public static function update_bigbluebuttonbn_recordingids($idrecording, $courseid, $bigbluebuttonbnid, $recordingid, $confirmation) {
		global $DB, $CFG;

		if($confirmation == "Y"){
			$sql = "UPDATE {$CFG->prefix}bigbluebuttonbn_recordings mbr
					INNER JOIN {$CFG->prefix}bigbluebuttonbn mbb on mbb.id = mbr.bigbluebuttonbnid
					SET recordingid = '$recordingid'
					WHERE mbr.id = $idrecording AND mbr.courseid = $courseid AND mbr.bigbluebuttonbnid = $bigbluebuttonbnid";
			echo $sql;
			echo "\n";
			$DB->execute($sql);
		}else{
			echo "Record Not Updated";
		}
	}


	/**
	 * The method update_bigbluebuttonbn_recordings does not return anything
	 * @return null
	 */
	public static function update_bigbluebuttonbn_recordingids_returns() {
		return null;
	}


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function delete_bigbluebuttonbn_recordings_parameters() {
		return new external_function_parameters(
			array(
					'idrecording' 		=> new external_value(PARAM_INT, 'ID of the recording'),
					'courseid' 			=> new external_value(PARAM_INT, 'ID of Moodle course'),
					'bigbluebuttonbnid'	=> new external_value(PARAM_INT, 'ID of the BBB instance'),
					'confirmation' 		=> new external_value(PARAM_RAW, 'String Y to delete the record')
				)
		);
	}


	/**
	 * Delete a specific recording in the table mdl_bigbluebuttonbn_recordings
	 * @param int $idrecording ID of the recording from the table mdl_bigbluebuttonbn_recordings
	 * @param int $courseid ID of the course
	 * @param int $bigbluebuttonbnid ID of the BBB instance, from table mdl_bigbluebuttonbn
	 * @param string $confirmation value "Y" to Update, if is different the query won't will run
	 * @return null
	 */
	public static function delete_bigbluebuttonbn_recordings($idrecording, $courseid, $bigbluebuttonbnid, $confirmation) {
		global $DB, $CFG;

		if($confirmation == "Y"){
			$sql = "DELETE FROM {$CFG->prefix}bigbluebuttonbn_recordings
					WHERE id = $idrecording AND courseid = $courseid AND bigbluebuttonbnid = $bigbluebuttonbnid";
			echo $sql;
			echo "\n";
			$DB->execute($sql);
		}else{
			echo "Record Not Deleted";
		}
	}


	/**
	 * The method delete_bigbluebuttonbn_recordings does not return anything
	 * @return null
	 */
	public static function delete_bigbluebuttonbn_recordings_returns() {
		return null;
	}
}

/**
 * HELPER FUNCTIONS  
 */
function filterProfileFields($field, $value){
	$filteredValue = '';

	switch ($field) {
		case PROFILE_FIELD_INSTITUTION_TYPE:
			$filteredValue = trim(format_string($value));

			if (preg_match('/International Organization/', $filteredValue) || preg_match('/Org Internacional/', $filteredValue) || preg_match('/Organización Internacional/', $filteredValue) || preg_match('/Org International/', $filteredValue)) {
				$filteredValue = "International Organization";
			}
			elseif (preg_match('/Local Government/', $filteredValue) || preg_match('/Gobierno Local/', $filteredValue) || preg_match('/Governo Local/', $filteredValue) || preg_match('/Organisme Local/', $filteredValue)) {
				$filteredValue = "Local Government";
			}
			elseif (preg_match('/Univ/Educational Center/', $filteredValue) || preg_match('/Univ/Centro Educativo/', $filteredValue) || preg_match('/Univ/Centro Educacional/', $filteredValue) || preg_match('/Univ/Centre d/', $filteredValue)) {
				$filteredValue = "Univ/Educational Center";
			}
			elseif (preg_match('/Research Center/', $filteredValue) || preg_match('/Centro de Investigación/', $filteredValue) || preg_match('/Centro de Pesquisa/', $filteredValue) || preg_match('/Centre de Recherche/', $filteredValue)) {
				$filteredValue = "Research Center";
			}
			elseif (preg_match('/State\/Province Government/', $filteredValue) || preg_match('/Gobierno del Estado\/Provincia/', $filteredValue) || preg_match('/Gobierno del Estado/', $filteredValue)  || preg_match('/Governo Estadual/', $filteredValue) || preg_match('/tat\/de Province/', $filteredValue)) {
				$filteredValue = "State/Province Government";
			}
			elseif (preg_match('/National Government/', $filteredValue) || preg_match('/Gobierno Nacional/', $filteredValue) || preg_match('/Governo Nacional/', $filteredValue)) {
				$filteredValue = "National Government";
			}
			elseif (preg_match('/NGOs\/Foundations/', $filteredValue) || preg_match('/ONGs \/ Fundaciones/', $filteredValue) || preg_match('/ONGs \/ Fundações/', $filteredValue) || preg_match('/ONG\/Fondation/', $filteredValue)) {
				$filteredValue = "NGOs/Foundations";
			}
			elseif (preg_match('/Private Sector/', $filteredValue) || preg_match('/Sector Privado/', $filteredValue) || preg_match('/Setor Privado/', $filteredValue) || preg_match('/Secteur privé/', $filteredValue)) {
				$filteredValue = "Private Sector";
			}

			break;

		case PROFILE_FIELD_GENDER:
			$filteredValue = trim(format_string($value));

			if (preg_match('/Female/', $filteredValue) || preg_match('/Feminino/', $filteredValue) || preg_match('/Femenino/', $filteredValue) || preg_match('/Féminin/', $filteredValue)) {
				$filteredValue = "F";
			}
			elseif (preg_match('/Male/', $filteredValue) || preg_match('/Masculino/', $filteredValue) || preg_match('/Masculin/', $filteredValue)) {
				$filteredValue = "M";
			}
			elseif (preg_match('/NC (Not Completed)/', $filteredValue) || preg_match('/NC (Não Concluído)/', $filteredValue) || preg_match('/NC (No Completado)/', $filteredValue) || preg_match('/Pas Achevé/', $filteredValue)) {
				$filteredValue = "NC (Not Completed)";
			}

			break;

		case PROFILE_FIELD_HIGHEST_DEGREE:
			$filteredValue = trim(format_string($value));

			if (preg_match('/4 Year College/', $filteredValue) || preg_match('/Graduación/', $filteredValue) || preg_match('/Graduação/', $filteredValue) || preg_match('/Licence/', $filteredValue)) {
				$filteredValue = "4 Year College";
			}
			elseif (preg_match('/High School/', $filteredValue) || preg_match('/Secundario/', $filteredValue) || preg_match('/2º Grau/', $filteredValue) || preg_match('/Enseignement Secondaire/', $filteredValue)) {
				$filteredValue = "High School";
			}
			elseif (preg_match('/Masters Degree/', $filteredValue) || preg_match('/Maestría/', $filteredValue) || preg_match('/Mestrado/', $filteredValue) || preg_match('/Master/', $filteredValue)) {
				$filteredValue = "Masters Degree";
			}
			elseif (preg_match('/Research Center/', $filteredValue) || preg_match('/Centro de Investigación/', $filteredValue) || preg_match('/Centro de Pesquisa/', $filteredValue)) {
				$filteredValue = "Other";
			}
			elseif (preg_match('/Ph.D/JD/MD/', $filteredValue) || preg_match('/Doctorado/', $filteredValue) || preg_match('/Doutorado/', $filteredValue)  || preg_match('/Doctorat/Doctorat en Médecine\/Doctorat de Droit/', $filteredValue)) {
				$filteredValue = "Ph.D/JD/MD";
			}

			break;

		case PROFILE_COUNTRY:
			$filteredValue = trim(format_string($value));

			$countries = get_string_manager()->get_list_of_countries();
			if (array_key_exists($filteredValue, $countries)){
				$filteredValue = $countries[$filteredValue];
			}else{
				$filteredValue = '';
			}

			break;
	}

	return $filteredValue;
}