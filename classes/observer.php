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
 * Observer for mod_scheduler.
 *
 * @package    mod_scheduler
 * @copyright  2017 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scheduler;
/**
 * Event observers for scheduler.
 */
class observer {
    /**
     * Creates a course group when slot is added.
     *
     * @param event\slot_added $event
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function slot_added(\mod_scheduler\event\slot_added $event): void {
        global $CFG;

        $slot = $event->get_slot();
        $scheduler = $slot->get_scheduler();

        // Create group upon slot creation only for group creation mode 1.
        if ((int) $scheduler->groupcreation !== 1) {
            return;
        }

        // Slot has to allow multiple bookers (groupslot).
        if (!$slot->is_groupslot()) {
            return;
        }

        require_once($CFG->dirroot . '/mod/scheduler/locallib.php');

        scheduler_create_coursegroup($slot);
    }

    /**
     * Adds booked students to the appropriate course group.
     *
     * @param \mod_scheduler\event\booking_added $event
     * @return void
     */
    public static function booking_added(\mod_scheduler\event\booking_added $event): void {
        course_group_helper::add_booked_students($event->get_slot());
    }

    /**
     * Removes students from the course group.
     *
     * @param event\booking_removed $event
     * @return void
     */
    public static function booking_removed(\mod_scheduler\event\booking_removed $event): void {
        $other = $event->other;

        if (!isset($other['studentid'], $other['bookinggroupid'])) {
            return;
        }

        $appointment = (object) [
            'studentid' => (int) $other['studentid'],
            'bookinggroupid' => (int) $other['bookinggroupid'],
        ];

        course_group_helper::remove_booked_students($event->get_slot(), $appointment);
    }
}
