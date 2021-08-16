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

namespace block_aprende_coursenavigation\tests;

use block_aprende_coursenavigation;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/aprende_coursenavigation/block_aprende_coursenavigation.php');

/**
 * Testsuite class for block course navigation.
 *
 * @package    block_aprende_coursenavigation
 * @copyright  David OC <davidherzlos@aprende.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_aprende_coursenavigation_testcase extends \advanced_testcase {

    protected $blockname;
    protected $course;
    protected $page;

    /**
     * SetUp for tests
     */
    protected function setUp(): void {
        $this->resetAfterTest();

        // Set up a normal course page for aprendetopics
        $this->course = $this->getDataGenerator()->create_course(array(
            'format' => 'aprendetopics'
        ));
        $this->page = new \moodle_page();
        $this->page->set_course($this->course);
        $this->page->set_pagetype('course-view-topics');
        $this->page->set_url('/course/view.php', [
            'id' => $this->course->id
        ]);
    }

    /**
     * @testdox Without any block instance set, block's content object should be empty
     * @test
    */
    public function test_block_content_object_empty_value(): void {
        // Default structure for block's content object
        $output = new \stdClass();
        $output->footer = '';
        $output->text = '';

        // Block's content object
        $block = new \block_aprende_coursenavigation();

        $expected = $output;
        $actual = $block->get_content();
        $this->assertEquals($expected, $actual, 'Values should be equals');
    }

    /**
     * @testdox Setting a block instance, instance's text property should not be empty
     * @test
     */
    public function test_block_instance_text_prop_non_empty(): void {
        global $USER, $PAGE;

        $PAGE = $this->page;

        $user = $this->getDataGenerator()->create_user();
        $user->profile['folio'] = '4';
        $this->setUser($user);

        // Set some necessary Amplitude settings for block creation.
        $this->apikey = set_config('amplitudeapikey', 'TESTAPIKEY999', 'theme_aprende');
        $this->config = set_config('amplitude_config', true, 'theme_aprende');
        $this->enabled = set_config('amplitude_enable', 1, 'theme_aprende');

        // Setup a block
        $record = $this->create_block_record($PAGE);
        $block = block_instance('aprende_coursenavigation', $record, $PAGE);

        $this->assertInstanceOf(\block_base::class, $block);

        $expected = true;
        $actual = $block->get_content()->text;
        $this->assertEquals($expected, !empty($actual), 'Values should be equals');
    }

    /**
     * @testdox Given a specific escenario, should_skip_activity() should return true if all the right settings are in place
     * @test
     */
    public function test_skipping_anactivity(): void {
        global $PAGE, $USER;

        // Set necessary configuration
        set_config('enable_activities_ab_test', true, 'format_aprendetopics');

        // Set up default student escenario for a course and an activity
        $course = $this->course;
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(array(
            'course' => $course
        ));

        // Create and enrol a student
        $user = self::getDataGenerator()->create_and_enrol($course, 'student');

        // Set it as request user
        $this->setUser($user);

        // Update page
        $PAGE = $this->page;

        // Setup a block
        $record = $this->create_block_record($PAGE);
        $block = block_instance('aprende_coursenavigation', $record, $PAGE);

        // Fetch the activity
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($quiz->cmid);

        // Required configurations are not in place
        $this->assertFalse($block->should_skip_activity($cm, $course), 'It should return false');

        // Provide course format options
        $course->activities_enabled = 1;
        $course->activitiessection = (string)$cm->id;

        // Provide the required user field
        $user->profile = [];
        $user->profile['folio'] = "4"; // Even folio id
        $this->setUser($user);

        // Required configuration are in place
        $expected = true;
        $actual = $block->should_skip_activity($cm, $course);
        $this->assertEquals($expected, $actual, 'Values should be equals');
    }

    public function test_get_content_automatically_expands_the_section_for_clases_magistrales() {
        global $PAGE;

        $opts = array('format' => 'microcourse');
        $course = self::getDataGenerator()->create_course($opts);
        $PAGE->set_course($course);

        $block = new block_aprende_coursenavigation();
        $this->assertTrue($block->course_is_microcourse());
    }

    /**
     * Utility method to create block record
     * TODO: Refactor this method into a plugin instance generator
     */
    protected function create_block_record(\moodle_page $page): \stdClass {
        global $DB;

        $blockrecord = new \stdClass;
        $blockrecord->blockname = 'aprende_coursenavigation';
        $blockrecord->parentcontextid = $page->context->id;
        $blockrecord->showinsubcontexts = true;
        $blockrecord->pagetypepattern = 'course-view-*';
        $blockrecord->subpagepattern = null;
        $blockrecord->defaultregion = 'side-pre';
        $blockrecord->defaultweight = 0;
        $blockrecord->configdata = '';
        $blockrecord->timecreated = time();
        $blockrecord->timemodified = $blockrecord->timecreated;
        $blockrecord->id = $DB->insert_record('block_instances', $blockrecord);

        return $blockrecord;
    }
}
