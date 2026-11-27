CHANGELOG
=========

Changes in v5.2-r2
----------------------------------

- Added configurable booking start and end dates.
- Added visibility settings controlling whether students can see other students booked into a slot.
- Improved booking and cancellation handling for students belonging to overlapping groups.
- Added automatic course-group creation for slots and individual bookings.
- Added comments when creating repeated slots.
- Added an activity setting to allow group bookings only or both individual and group bookings.
- Added optional online meeting links, with a join button shown to students after booking.
- Added an option to copy slots when duplicating a Scheduler activity, without copying student bookings.
- Ensured duplicated slots use independent automatically created course groups.
- Moved additional navigation tabs into the “More” menu.
- Fixed duplicate bookings.
- Added an option to retain attended appointments in the calendar.
- Improved course overview integration, activity information, layouts, and icons.
- Updated backup and restore handling for the new settings and slot fields.

Changes in v5.2-r1
----------------------------------

- support for Moodle 5.0, 5.1, 5.2
- add CHANGES.md for user-visible changes
- Migrated CI testing to the shared Learnweb Moodle workflow
- Added a test matrix covering Moodle 4.5, 5.0, 5.1, and 5.2 with multiple supported PHP versions and database systems