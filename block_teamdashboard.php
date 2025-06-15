<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');

class block_teamdashboard extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_teamdashboard');
    }

    
    /**
     * Returns the content of the Team Dashboard block.
     *
     * Shows course progress for participants visible to the current trainer.
     *
     * @return stdClass
     */

    public function get_content() {
        global $OUTPUT, $USER, $DB, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $output = '';
        $courses = get_courses(['sortorder' => 'visible']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        if (!$teacherrole) {
            $this->content->text = 'Rolle "teacher" nicht gefunden.';
            return $this->content;
        }

        $output .= '<div style="font-family:Arial,sans-serif; font-size:0.95em; max-width:800px;">';
        $output .= '<div style="text-align:right; margin-bottom:12px; font-size:0.8em; color:#444; display:flex; flex-wrap:wrap; gap:0.6em; justify-content:flex-end;">
<span style="display:inline-flex; align-items:center; gap:0.25em;"><span style="width:6px; height:14px; background:#4caf50; display:inline-block;"></span>'. get_string('legend_completed', 'block_teamdashboard') .'</span>
<span style="display:inline-flex; align-items:center; gap:0.25em;"><span style="width:6px; height:14px; background:#ffeb3b; display:inline-block;"></span>'. get_string('legend_inprogress', 'block_teamdashboard') .'</span>
<span style="display:inline-flex; align-items:center; gap:0.25em;"><span style="width:6px; height:14px; background:#e74c3c; display:inline-block;"></span>'. get_string('legend_overdue', 'block_teamdashboard') .'</span>
</div>';

        foreach ($courses as $course) {
            if ($course->id == 1) continue;
            $contextcourse = context_course::instance($course->id);
            if (!user_has_role_assignment($USER->id, $teacherrole->id, $contextcourse->id)) continue;

            $coursename = is_array($course->fullname) ? '[Kursname ungültig]' : $course->fullname;
            $coursename = is_string($coursename) ? $coursename : '[Kein gültiger Name]';
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

            $completion = new completion_info($course);
            
        
            $groupmode = groups_get_course_groupmode($course);
            $canseeallgroups = has_capability('moodle/site:accessallgroups', $contextcourse);

            if ($groupmode && !$canseeallgroups) {
                $trainergroups = groups_get_user_groups($course->id, $USER->id);
                $users = [];
                foreach ($trainergroups[0] as $groupid) {
                    $members = groups_get_members($groupid, 'u.*');
                    foreach ($members as $id => $member) {
                        $users[$id] = $member;
                    }
                }
            } else {
                $users = get_enrolled_users($contextcourse, 'moodle/course:viewparticipants');
            }


            $studentrole = $DB->get_record('role', array('shortname' => 'student'));
            $filteredusers = array();
            foreach ($users as $u) {
                if (user_has_role_assignment($u->id, $studentrole->id, $contextcourse->id)) {
                    $filteredusers[] = $u;
                }
            }

            $completed = $inprogress = $overdue = 0;
            $now = time();
            $hascompletion = $completion->is_enabled();

            foreach ($filteredusers as $user) {
                if (!$hascompletion) { $inprogress++; continue; }
                if ($completion->is_course_complete($user->id)) {
                    $completed++;
                } elseif (!empty($course->enddate) && $now > $course->enddate) {
                    $overdue++;
                } else {
                    $inprogress++;
                }
            }

            $total = max(1, count($filteredusers));
            $barstyle = 'height:16px; border-radius:4px;';
            $output .= '<div style="border:1px solid #ccc; padding:12px; margin-bottom:16px; border-radius:8px; background:#f9f9f9;">';
            $output .= '<strong><a href="' . $courseurl->out() . '">' . htmlspecialchars($coursename) . '</a></strong><br><br>';
            $output .= '<div style="display:flex; width:100%; overflow:hidden; background:#eee; ' . $barstyle . '">';
            $output .= '<div style="width:' . ($completed / $total * 100) . '%; background:#4caf50;"></div>';
            $output .= '<div style="width:' . ($inprogress / $total * 100) . '%; background:#ffeb3b;"></div>';
            $output .= '<div style="width:' . ($overdue / $total * 100) . '%; background:#e74c3c;"></div>';
            $output .= '</div>';
            $output .= '<div style="margin-top:6px; font-size:0.85em; color:#666;">👥 ' . get_string('participantslabel', 'block_teamdashboard') . ': ' . count($filteredusers) . '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $this->content->text = $output;
        return $this->content;
    }
}
