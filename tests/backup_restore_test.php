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

namespace mod_scheduler;

/**
 * Unit tests for scheduler activity duplication.
 *
 * @package    mod_scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Load the backup and restore infrastructure.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Tests that duplication preserves slot details, teacher assignments and group
     * restrictions without copying bookings or modifying the original activity.
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     *
     * @covers \backup_scheduler_activity_structure_step
     * @covers \restore_scheduler_activity_structure_step
     */
    public function test_duplicate_with_copying_slots(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();

        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');

        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member([
                'groupid' => $group->id,
                'userid' => $student->id,
        ]);

        // Create a scheduler with one booked slot.
        $scheduler = $generator->create_module(
            'scheduler',
            ['course' => $course->id, 'copyslotsonduplicate' => 1],
            ['slottimes' => [time() + DAYSECS], 'slotteachers' => [$teacher->id], 'slotstudents' => [[$student->id]] ]
        );

        $originalslot = $DB->get_record('scheduler_slots', ['schedulerid' => $scheduler->id], '*', MUST_EXIST);

        $DB->set_field('scheduler_slots', 'coursegroupid', $group->id, ['id' => $originalslot->id]);

        $this->assertEquals(1, $DB->count_records('scheduler_appointment', ['slotid' => $originalslot->id]));

        $duplicatecm = \core_courseformat\formatactions::cm($course)->duplicate($scheduler->cmid);

        $this->assertNotNull($duplicatecm);

        $duplicateid = (int) $duplicatecm->instance;

        // A separate Scheduler was created.
        $this->assertNotEquals($scheduler->id, $duplicateid);

        // The duplicate contains one slot.
        $this->assertEquals(1, $DB->count_records('scheduler_slots', ['schedulerid' => $duplicateid]));

        $copiedslot = $DB->get_record('scheduler_slots', ['schedulerid' => $duplicateid], '*', MUST_EXIST);

        // The copied slot belongs to the new Scheduler.
        $this->assertEquals($duplicateid, $copiedslot->schedulerid);

        // Teacher assignment and group restriction are preserved.
        $this->assertEquals($teacher->id, $copiedslot->teacherid);
        $this->assertEquals($group->id, $copiedslot->coursegroupid);

        // The original slot's assignments remain unchanged.
        $unchangedslot = $DB->get_record('scheduler_slots', ['id' => $originalslot->id], '*', MUST_EXIST);

        $this->assertEquals($scheduler->id, $unchangedslot->schedulerid);
        $this->assertEquals($teacher->id, $unchangedslot->teacherid);
        $this->assertEquals($group->id, $unchangedslot->coursegroupid);

        // The original booking remains.
        $this->assertEquals(1, $DB->count_records('scheduler_appointment', ['slotid' => $originalslot->id]));

        // The copied slot is a separate record with the same scheduling details.
        $this->assertNotEquals($originalslot->id, $copiedslot->id);
        $this->assertEquals($originalslot->starttime, $copiedslot->starttime);
        $this->assertEquals($originalslot->duration, $copiedslot->duration);
        $this->assertEquals($originalslot->teacherid, $copiedslot->teacherid);
        $this->assertEquals(
            $originalslot->appointmentlocation,
            $copiedslot->appointmentlocation
        );

        // The original booking was not copied.
        $this->assertEquals(0, $DB->count_records('scheduler_appointment', ['slotid' => $copiedslot->id]));
    }
}
