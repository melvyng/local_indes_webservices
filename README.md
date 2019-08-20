# local_indes_webservices
This plugin contains a list of web services functions that can be used by third party plugins or external websites.
Web service plugin for Moodle 3.X
------------------------------------------

To test:
1- install the plugin in /local/indes_webservices/
2- Get the token id for the service "INDES Web Service" installed at Site administration > Plugins > Web services > Manage tokens
3- Test any of the following functions in your Moodle https://yourmoodle/webservice/rest/server.php?moodlewsrestformat=json&wstoken=[yourtoken]&wsfunction=[function_name]&parameters


List of functions included:
- indes_webservices_enrol_student : It enrols students by its email and the course id
- indes_webservices_get_categories : Get the ID & category names from the site
- indes_webservices_get_courses_with_offering : Get list of courses with Item or Offering numbers stored in the Course ID Number
- indes_webservices_get_enrollment_participants_category : Get the enrollment participants within a course or category
- indes_webservices_get_gradable_activities_course : Get the final course grade(s) and gradable activities for a single course for one or more users
- indes_webservices_get_grade_participant : Get the final course grade for a single user in a course
- indes_webservices_get_grades_course : Get the final course grade(s) for a single course for one or more users
- indes_webservices_get_grades_participant_category : Get the enrolled & dropout participants with their grade from a category (courses)
- indes_webservices_get_questionnaire_course : Get the questionnaire(s), question and answers for a single course
- indes_webservices_get_sm_participants : Get the enrolled & completed participants from a date (courses) for SurveyMonkey
- indes_webservices_get_sm_participants_by_course : Get the enrolled & completed participants from a course for SurveyMonkey
- indes_webservices_get_sm_surveys_by_courses : Get list of surveymonkey surveys by courses
- indes_webservices_get_sm_users_completed : Get list of users that already completed surveymonkey surveys by each course
- indes_webservices_sync_facilitators	: Return the list of facilitators assigned to a course since the last synchronization process
- indes_webservices_sync_outputs : Return the list of new outputs since the last synchronization process
- indes_webservices_sync_participants_dropout	: Return the list of participants who were droped out from course
- indes_webservices_sync_participants_profile_updated	: Return the list of participants who had their profile changed since the last synchronization process
- indes_webservices_sync_registrations : Return the list of new registrations since the last synchronization process
- indes_webservices_sync_total_registrations : It retrieves the total number of participants of those courses starting today.
- indes_webservices_unenrol_student : It unenrols students by its email and the course id
- indes_webservices_validate_certificate : Verify if the code given is a valid certificate
- limesurvey_mark_as_completed : Mark a LimeSurvey activity as completed for a specific participant
- surveymonkey_mark_as_completed : Mark a SurveyMonkey activity as completed for a specific participant
