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
 * Define all the restore steps that will be used by the restore_scheduler_activity_task
 *
 * @package    mod_scheduler
 * @copyright  2016 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one scheduler activity
 *
 * @copyright  2016 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_scheduler_activity_structure_step extends restore_activity_structure_step
{
    /** @var bool Whether this is a duplication within the same course. */
    protected $isduplicate = false;

    /** @var bool Whether slots may be copied without appointment data. */
    protected $copyslots = false;

    /**
     * define_structure
     *
     * @return array
     */
    protected function define_structure() {
        global $DB;

        $userinfo = $this->get_setting_value('userinfo');
        $task = $this->get_task();

        $restoreinfo = $DB->get_record(
            'backup_controllers',
            ['backupid' => $task->get_restoreid()],
            'type, purpose, interactive',
            MUST_EXIST
        );

        $this->isduplicate =
                $restoreinfo->type === backup::TYPE_1ACTIVITY
                && (int) $restoreinfo->purpose === backup::MODE_IMPORT
                && (bool) $restoreinfo->interactive === backup::INTERACTIVE_NO
                && $task->is_samesite()
                && (int) $task->get_old_courseid() === (int) $this->get_courseid();

        $paths = [new restore_path_element('scheduler', '/activity/scheduler')];
        if ($userinfo || $this->isduplicate) {
            $paths[] = new restore_path_element('scheduler_slot', '/activity/scheduler/slots/slot');
        }

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'scheduler_appointment',
                '/activity/scheduler/slots/slot/appointments/appointment'
            );
        }

        return $this->prepare_activity_structure($paths);

        $userinfo = $this->get_setting_value('userinfo');

        $scheduler = new restore_path_element('scheduler', '/activity/scheduler');
        $paths[] = $scheduler;

        if ($userinfo) {
            $slot = new restore_path_element('scheduler_slot', '/activity/scheduler/slots/slot');
            $paths[] = $slot;

            $appointment = new restore_path_element(
                'scheduler_appointment',
                '/activity/scheduler/slots/slot/appointments/appointment'
            );
            $paths[] = $appointment;
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * process_scheduler
     *
     * @param stdClass $data
     */
    protected function process_scheduler($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->scale < 0) { // Scale found, get mapping.
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        if (is_null($data->gradingstrategy)) { // Catch inconsistent data created by pre-1.9 DB schema.
            $data->gradingstrategy = 0;
        }

        if (!isset($data->bookingtype)) {
            $data->bookingtype = get_config('mod_scheduler', 'mixindivgroup') ? 1 : 2;
        }

        if ($data->bookingrouping > 0) {
            if ($this->isduplicate) {
                $DB->get_record(
                    'groupings',
                    ['id' => $data->bookingrouping, 'courseid' => $this->get_courseid()],
                    'id',
                    MUST_EXIST
                );
            } else {
                $data->bookingrouping = $this->get_mappingid('grouping', $data->bookingrouping);
            }
        }

        $data->groupcreation = (int) ($data->groupcreation ?? 0);
        $data->copyslotsonduplicate = (int) ($data->copyslotsonduplicate ?? 0);
        $this->copyslots = $this->isduplicate && !empty($data->copyslotsonduplicate);

        // Insert the scheduler record.
        $newitemid = $DB->insert_record('scheduler', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * process_scheduler_slot
     *
     * @param stdClass $data
     */
    protected function process_scheduler_slot($data) {
        global $DB;

        $userinfo = $this->get_setting_value('userinfo');
        if (!$userinfo && !$this->copyslots) {
            return self::SKIP_ALL_CHILDREN;
        }
        $data = (object)$data;
        $data->meetingurl = $data->meetingurl ?? '';
        $oldid = $data->id;

        $data->schedulerid = $this->get_new_parentid('scheduler');
        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->emaildate = $this->apply_date_offset($data->emaildate);
        $data->hideuntil = $this->apply_date_offset($data->hideuntil);
        if ($this->copyslots && !$userinfo) {
            // Same-site, same-course duplication retains existing references.
            if (!empty($data->teacherid)) {
                $DB->get_record(
                    'user',
                    ['id' => $data->teacherid, 'deleted' => 0],
                    'id',
                    MUST_EXIST
                );
            }

            $data->coursegroupid = (int) ($data->coursegroupid ?? 0);

            if ($data->coursegroupid > 0) {
                $DB->get_record(
                    'groups',
                    [
                                'id' => $data->coursegroupid,
                                'courseid' => $this->get_courseid(),
                        ],
                    'id',
                    MUST_EXIST
                );
            }
        } else {
            $data->teacherid = $this->get_mappingid('user', $data->teacherid);

            $data->coursegroupid = !empty($data->coursegroupid)
                    ? $this->get_mappingid('group', $data->coursegroupid, 0)
                    : 0;
        }

        $newitemid = $DB->insert_record('scheduler_slots', $data);
        $this->set_mapping('scheduler_slot', $oldid, $newitemid, true);
    }

    /**
     * process_scheduler_appointment
     *
     * @param stdClass $data
     */
    protected function process_scheduler_appointment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->slotid = $this->get_new_parentid('scheduler_slot');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $data->studentid = $this->get_mappingid('user', $data->studentid);

        $data->bookinggroupid = !empty($data->bookinggroupid)
            ? $this->get_mappingid('group', $data->bookinggroupid, 0) : 0;

        $newitemid = $DB->insert_record('scheduler_appointment', $data);
        $this->set_mapping('scheduler_appointment', $oldid, $newitemid, true);
    }

    /**
     * after_execute
     */
    protected function after_execute() {
        // Add scheduler related files.
        $this->add_related_files('mod_scheduler', 'intro', null);
        $this->add_related_files('mod_scheduler', 'bookinginstructions', null);
        $this->add_related_files('mod_scheduler', 'slotnote', 'scheduler_slot');
        $this->add_related_files('mod_scheduler', 'appointmentnote', 'scheduler_appointment');
        $this->add_related_files('mod_scheduler', 'teachernote', 'scheduler_appointment');
        $this->add_related_files('mod_scheduler', 'studentfiles', 'scheduler_appointment');
    }
}
