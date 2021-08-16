<?php
// This file is part of The Course Module Navigation Block
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
 * Course contents block generates a table of course contents based on the section descriptions.
 *
 * @package    block_aprende_coursenavigation
 * @copyright  2019 Pimenko <contact@pimenko.com> <pimenko.com>
 * @author     Sylvain Revenu | Nick Papoutsis | Bas Brands | Pimenko
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

/**
 * Define the block course navigation.
 *
 * @package    block_aprende_coursenavigation
 * @copyright  2019 Pimenko <contact@pimenko.com> <pimenko.com>
 * @author     Sylvain Revenu | Nick Papoutsis | Bas Brands | Pimenko
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_aprende_coursenavigation extends block_base {

    /**
     * Initializes the block, called by the constructor.
     */
    public function init() {
        $this->title = get_string(
                'pluginname',
                'block_aprende_coursenavigation'
        );
    }

    /**
     *  Allow parameters in admin settings
     */
    public function has_config() {
        return true;
    }

    /**
     * Amend the block instance after it is loaded.
     */
    public function specialization() {
        if (!empty($this->config->blocktitle)) {
            $this->title = $this->config->blocktitle;
        } else {
            $this->title = get_string(
                    'config_blocktitle_default',
                    'block_aprende_coursenavigation'
            );
        }
    }

    /**
     * Which page types this block may appear on.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
                'site-index' => true,
                'course-view-*' => true
        ];
    }

    /**
     * Populate this block's content object.
     *
     * @return stdClass|stdObject
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_content() {
        global $DB, $PAGE, $USER;

        if (!is_null($this->content)) {
            return $this->content;
        }

        [$selected, $intab] = $this->get_page_params();

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if ($PAGE->pagelayout == 'admin') {
            return $this->content;
        }

        $format = course_get_format($this->page->course);
        $course = $format->get_course(); // Needed to have numsections property available.

        if (!$format->uses_sections()) {
            if (debugging()) {
                $this->content->text = '';
            }
            return $this->content;
        }

        $sections = $format->get_sections();

        if (empty($sections)) {
            return $this->content;
        }

        $context = context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course);

        $templatecontext = new stdClass();

        $completioninfo = new completion_info($course);

        $continuationclass = '\block_aprendeoverview\course_continuation_info';

        // Get last viewed section
        if (class_exists($continuationclass)) {
            $continfo = new \block_aprendeoverview\course_continuation_info($course, $USER);
            if (
                !empty($link = $continfo->continuation_link) &&
                $continfo->continuation_link instanceof moodle_url
            ) {
                $lastcmid = $link->get_param('id');

                try {
                    $lastcm = $modinfo->get_cm($lastcmid);
                    $lastsection = $modinfo->get_section_info($lastcm->sectionnum);

                } catch (\Exception $e) {
                    debugging("Heads up: {$e->getMessage()}");
                }
            }
        }

        if ($completioninfo->is_enabled()) {
            $templatecontext->coursecompletionon = true;
        }

        $completionok = [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS
        ];

        $thiscontext = context::instance_by_id($this->page->context->id);

        $inactivity = false;
        $myactivityid = 0;

        if ($thiscontext->get_level_name() == get_string('activitymodule')) {
            // Uh-oh we are in a activity.
            $inactivity = true;
            if ($cm = $DB->get_record_sql(
                    "SELECT cm.*, md.name AS modname
                                           FROM {course_modules} cm
                                           JOIN {modules} md ON md.id = cm.module
                                           WHERE cm.id = ?",
                    [$thiscontext->instanceid]
            )) {
                $myactivityid = $cm->id;
            }
        }

        if ($format instanceof format_digidagotabs || $format instanceof format_horizontaltabs) {
            $coursesections = $DB->get_records(
                    'course_sections',
                    ['course' => $course->id]
            );
            $mysection = 0;
            foreach ($coursesections as $cs) {
                $csmodules = explode(
                        ',',
                        $cs->sequence
                );
                if (in_array(
                        $myactivityid,
                        $csmodules
                )) {
                    $mysection = $cs->id;
                }
            }

            if ($mysection) {
                if (($format instanceof format_digidagotabs && $DB->get_records(
                                        'format_digidagotabs_tabs',
                                        [
                                                'courseid' => $course->id,
                                                'sectionid' => $mysection
                                        ]
                                )) || ($format instanceof format_horizontaltabs && $DB->get_records(
                                        'format_horizontaltabs_tabs',
                                        [
                                                'courseid' => $course->id,
                                                'sectionid' => $mysection
                                        ]
                                ))) {
                    // This is a module inside a tab of the Dynamic tabs course format.
                    // Prevent showing of this menu.
                    return $this->content;
                }
            }
        }

        $templatecontext->inactivity = $inactivity;

        if (count($sections) > 1) {
            $templatecontext->hasprevnext = true;
            $templatecontext->hasnext = true;
            $templatecontext->hasprev = true;
        }

        $courseurl = new moodle_url(
                '/course/view.php',
                ['id' => $course->id]
        );
        $templatecontext->courseurl = $courseurl->out();
        $sectionnums = [];
        foreach ($sections as $section) {
            $sectionnums[] = $section->section;
        }

        // Get base amplitude data.
        $eventattrs = [];
        if(class_exists('\theme_aprende\amplitude')){
            $eventattrs = (\theme_aprende\amplitude::getInstance())->event_attrs;
        }
        foreach ($sections as $section) {
            $i = $section->section;

            if (!$section->uservisible) {
                if (!get_config(
                        'block_aprende_coursenavigation',
                        'toggleshowrestricted')) {
                    continue;
                }

            }

            if (!empty($section->name)) {
                $title = format_string(
                        $section->name,
                        true,
                        ['context' => $context]
                );
            } else {
                $summary = file_rewrite_pluginfile_urls(
                        $section->summary,
                        'pluginfile.php',
                        $context->id,
                        'course',
                        'section',
                        $section->id
                );
                $summary = format_text(
                        $summary,
                        $section->summaryformat,
                        [
                                'para' => false,
                                'context' => $context
                        ]
                );
                $title = $format->get_section_name($section);
            }

            $thissection = new stdClass();
            $thissection->number = $i;
            $thissection->title = $title;
            $thissection->url = $format->get_view_url($section);
            $thissection->selected = false;

            if (isset($lastsection)) {
                if ($section == $lastsection) {
                    $thissection->lastviewed = true;
                }
            }

            if (strlen($title) >= 40) {
                $thissection->shouldbeshort = true;
            }

            if (get_config(
                            'block_aprende_coursenavigation',
                            'toggleclickontitle'
                    ) == 2) {
                // Display the menu.
                $thissection->collapse = true;
            } else {
                // Go to link.
                $thissection->collapse = false;
            }

            if (get_config(
                            'block_aprende_coursenavigation',
                            'togglecollapse'
                    ) == 2) {
                $thissection->selected = true;
            }

            // Show only titles.
            if (get_config(
                            'block_aprende_coursenavigation',
                            'toggletitles'
                    ) == 2) {
                // Show only titles.
                $thissection->onlytitles = true;
            } else {
                // Show  titles and contents.
                $thissection->onlytitles = false;
            }

            // Show the restricted section
            if (!$section->uservisible) {
                $thissection->restricted = true;
                $thissection->conditions = \core_availability\info::format_info(
                    $section->availableinfo, $course
                );
            }

            // Show subtitle section property if exist
            if (isset($section->subtitle) && isset($section->subtitle_icon)) {
                $thissection->sectionlabel = $section->subtitle;
                $thissection->sectionlabelicon = $section->subtitle_icon;
            }

            $thissection->modules = [];
            if (!empty($modinfo->sections[$i])) {
                foreach ($modinfo->sections[$i] as $modnumber) {

                    $module = $modinfo->cms[$modnumber];

                    if ($module->deletioninprogress) {
                        continue;
                    }

                    if ((get_config('block_aprende_coursenavigation', 'toggleshowlabels') == 1) &&
                        ($module->modname == 'label')) {
                        continue;
                    }

                    // Practical activities experiment
                    if ($this->should_skip_activity($module, $course)) {
                        continue;
                    }

                    if (!$module->visible || !$module->visibleoncoursepage) {
                        continue;
                    }

                    if (!$module->uservisible) {
                        if (!get_config(
                                'block_aprende_coursenavigation',
                                'toggleshowrestricted')) {
                            continue;
                        }

                    }

                    $thismod = new stdClass();

                    if ($inactivity) {
                        if ($myactivityid == $module->id) {
                            $thissection->selected = true;
                            $thismod->active = 'active';
                        }
                    }
                    $thismod->name = format_string(
                            $module->name,
                            true,
                            ['context' => $context]
                    );
                    $thismod->url = $module->url;
                    $thismod->onclick = $module->onclick;

                    if (!$module->uservisible) {
                        $thismod->url = '';
                        $thismod->onclick = '';
                        $thismod->disabled = 'true';
                        $thismod->conditions = \core_availability\info::format_info(
                            $module->availableinfo, $course
                        );
                    } else {
                        $thismod->available = 'true';
                    }

                    if ($module->modname == 'label') {
                        $htmltitleregexp = '/<h[1-6] class="content-separator">(?<titletext>.+?)<\/h[1-6]>/iu';

                        $titlematch = [];
                        if (!preg_match($htmltitleregexp, str_replace(array("\r","\n"),"",$module->content), $titlematch)) {
                            continue;
                        }

                        $thismod->url = '';
                        $thismod->onclick = '';
                        $thismod->label = 'true';
                        $thismod->labelcontent = htmlspecialchars_decode($titlematch['titletext']);
                    }

                    $statusclass = '\format_aprendetopics\status';
                    if ($module->uservisible && class_exists($statusclass)) {
                        $status = new $statusclass($module->id);
                        if ($status->optional) {
                            $thismod->optional = true;
                        }
                    }

                    $hascompletion = $completioninfo->is_enabled($module);
                    if ($hascompletion) {
                        $thismod->completionon = true;
                    }

                    $completiondata = $completioninfo->get_data(
                            $module,
                            true
                    );

                    if (in_array(
                            $completiondata->completionstate,
                            $completionok
                    )) {
                        $thismod->completeclass = 'completed';
                        $thismod->completed = true;
                    }

                    if ($completiondata->completionstate == COMPLETION_COMPLETE_FAIL) {
                        $thismod->completedfail = true;
                    }

                    // Automatically expand this mod's section on all courses using clases magistrales format.
                    $thismod->sectionexpanded = $this->course_is_microcourse();

                    if (isset($PAGE->cm->url) && $module->url === $PAGE->cm->url) {
                        $thismod->currentinpage = true;
                        $thismod->sectionexpanded = true;
                    }

                    // Add amplitude data for module.
                    $attrs = (array) $eventattrs;
                    $attrs['section_name'] = isset($thissection->sectionlabel) ? $thissection->sectionlabel : '';
                    $attrs['activity_name'] = $module->name;
                    $attrs['activity_type'] = $module->modname;
                    $moduleevent = [
                        'tag' => 'navigation - select activity',
                        'event_attrs' => $attrs,
                    ];
                    $thismod->module_amp_json = json_encode($moduleevent);

                    $thissection->modules[] = $thismod;
                }
                $thissection->hasmodules = (count($thissection->modules) > 0);
                $templatecontext->sections[] = $thissection;

            }
            if ($thissection->selected) {

                $pn = $this->get_prev_next(
                        $sectionnums,
                        $thissection->number
                );

                $courseurl = new moodle_url(
                        '/course/view.php',
                        [
                                'id' => $course->id,
                                'section' => $i
                        ]
                );
                $templatecontext->courseurl = $courseurl->out();

                if ($pn->next === false) {
                    $templatecontext->hasnext = false;
                }
                if ($pn->prev === false) {
                    $templatecontext->hasprev = false;
                }

                $prevurl = new moodle_url(
                        '/course/view.php',
                        [
                                'id' => $course->id,
                                'section' => $pn->prev
                        ]
                );
                $templatecontext->prevurl = $prevurl->out(false);

                $currurl = new moodle_url(
                        '/course/view.php',
                        [
                                'id' => $course->id,
                                'section' => $thissection->number
                        ]
                );
                $templatecontext->currurl = $currurl->out(false);

                $nexturl = new moodle_url(
                        '/course/view.php',
                        [
                                'id' => $course->id,
                                'section' => $pn->next
                        ]
                );
                $templatecontext->nexturl = $nexturl->out(false);
            }

            // Add amplitude data to section.
            $attrs = (array) $eventattrs;
            $attrs['section_name'] = isset($thissection->sectionlabel) ? $thissection->sectionlabel : '';
            $sectionevent = [
                'tag' => 'navigation - view section details',
                'event_attrs' => $attrs,
            ];
            $thissection->section_amp_json = json_encode($sectionevent);

        }
        if ($intab) {
            $templatecontext->inactivity = true;
        }
        $templatecontext->coursename = $course->fullname;
        $category = core_course_category::get($course->category, IGNORE_MISSING, true);

        if (!is_null($category)) {
            $templatecontext->coursecategory = $category->get_formatted_name();
        }

        $templatecontext->config = $this->config;
        $renderer = $this->page->get_renderer(
                'block_aprende_coursenavigation',
                'nav'
        );

        $this->templatecontext = $templatecontext;
        $this->content->text = $renderer->render_nav($templatecontext);
        return $this->content;
    }

    public function get_template_context() {
        return $this->templatecontext;
    }

    /**
     *
     * Function to get the previous and next values in an array.
     *
     * @param $array
     * @param $current
     * @return stdClass
     */
    private function get_prev_next($array, $current) {
        $pn = new stdClass();

        $hascurrent = $pn->next = $pn->prev = false;

        foreach ($array as $a) {
            if ($hascurrent) {
                $pn->next = $a;
                break;
            }
            if ($a == $current) {
                $hascurrent = true;
            } else {
                if (!$hascurrent) {
                    $pn->prev = $a;
                }
            }
        }
        return $pn;
    }

    /**
     * Returns the navigation.
     *
     * @return navigation_node The navigation object to display
     */
    protected function get_navigation() {
        $this->page->navigation->initialise();
        return clone($this->page->navigation);
    }

    /**
     * @return mixed[]
     * @throws coding_exception
     */
    protected function get_page_params() {
        return array(
            optional_param('section', null, PARAM_INT),
            optional_param('dtab', null, PARAM_TEXT)
        );
    }

    /**
     * Returns the true if this activity is part of the ap experiment and should be skipped, false if not
     * @param $cm cm_info object
     * @param $course stdClass course object
     *
     * @return navigation_node The navigation object to display
     */
    public function should_skip_activity(cm_info $cm, stdClass $course): bool {
        global $USER;

        $settingsdefined = $course->format === 'aprendetopics' &&
            $course->activities_enabled && !empty($course->activitiessection) &&
            isset($USER->profile);

        if ($this->page->user_is_editing() || !$settingsdefined) {
            return false;
        }

        // The settings are defined, validate them
        $cminlist = in_array($cm->id, explode(",", $course->activitiessection));
        $useristarget = array_key_exists('folio', $USER->profile) && (int)$USER->profile['folio'] > 0 &&
            (int)$USER->profile['folio'] % 2 == 0 && get_config('format_aprendetopics', 'enable_activities_ab_test') == 1;
        return  $cminlist && $useristarget;
    }

    /**
     * Simple utility function to determine if the current course
     * is in the microcourse format.
     *
     * @return bool
     */
    public function course_is_microcourse() {
        global $PAGE;

        if ($PAGE->course->format == 'microcourse') {
            return true;
        }

        return false;
    }
}
