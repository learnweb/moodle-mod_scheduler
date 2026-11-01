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
 * Helpers for synchronizing generated course groups with slot bookings.
 *
 * @package    mod_scheduler
 * @copyright  2026 L. Herfeldt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scheduler;
/**
 * Updates generated course group memberships from individual slot bookings.
 */
class course_group_helper {
    /**
     * Adds individually booked students to the slot's generated course group.
     *
     * @param model\slot $slot
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function add_booked_students(\mod_scheduler\model\slot $slot): void {
        global $CFG;
        global $DB;

        $scheduler = $slot->get_scheduler();
        $mode = (int) $scheduler->groupcreation;

        // Only process automatic group creation modes.
        if (!in_array($mode, [1, 2], true)) {
            return;
        }

        // Slot has to allow multiple bookers (groupslot).
        if (!$slot->is_groupslot()) {
            return;
        }

        $studentids = $DB->get_fieldset_select(
            'scheduler_appointment',
            'DISTINCT studentid',
            'slotid = :slotid AND bookinggroupid = 0',
            ['slotid' => $slot->id]
        );

        if (!$studentids) {
            return;
        }

        require_once($CFG->dirroot . '/mod/scheduler/locallib.php');
        require_once($CFG->dirroot . '/group/lib.php');

        $groupid = \scheduler_create_coursegroup($slot);
        foreach ($studentids as $studentid) {
            \groups_add_member($groupid, $studentid);
        }
    }

    /**
     * Removes individually booked students from the slot's generated course group.
     *
     * @param model\slot $slot
     * @param \stdClass $appointment
     * @return void
     * @throws \dml_exception
     */
    public static function remove_booked_students(\mod_scheduler\model\slot $slot, \stdClass $appointment): void {
        global $CFG, $DB;

        // Existing group bookings do not control generated group memberships.
        if ((int) $appointment->bookinggroupid !== 0) {
            return;
        }

        $scheduler = $slot->get_scheduler();
        $mode = (int) $scheduler->groupcreation;

        // Only process automatic group creation modes.
        if (!in_array($mode, [1, 2], true)) {
            return;
        }

        $groupid = $DB->get_field(
            'scheduler_slots',
            'coursegroupid',
            ['id' => $slot->id]
        );

        if (!$groupid || !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $scheduler->course])) {
            return;
        }

        require_once($CFG->dirroot . '/group/lib.php');

        \groups_remove_member($groupid, (int) $appointment->studentid);
    }
}
