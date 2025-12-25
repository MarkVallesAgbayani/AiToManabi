# Teacher Notification System Documentation

## Overview
The Teacher Notification System provides a centralized notification hub for teachers, similar to the admin notification system. It displays categorized notifications about student progress, engagement alerts, course updates, and admin messages.

## Features

### Notification Categories
1. **Student Progress**
   - Quiz completions
   - Course completions
   - Low performance alerts

2. **Engagement Alerts**
   - Inactive students (7+ days without activity)
   - Students struggling with multiple quizzes

3. **Course & System Updates**
   - New enrollments
   - Course milestones (10, 25, 50, 100 enrollments)

4. **Admin Notifications**
   - System announcements
   - Course status changes by admin

## File Structure
```
Teacher Notification/
├── teacher_notifications.php    # Main notification class
├── api.php                     # AJAX API endpoints
├── teacher_notifications.css   # Styling
├── teacher_notifications.js    # JavaScript functionality
└── README.md                   # This documentation
```

## Installation

### 1. Include the notification system in your teacher pages:

```php
// At the top of your teacher PHP file
require_once 'Teacher Notification/teacher_notifications.php';

// Initialize the notification system
$teacherNotificationSystem = initializeTeacherNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
```

### 2. Add the CSS and JS assets:

```php
// In the <head> section
echo $teacherNotificationSystem->renderNotificationAssets();
```

### 3. Add the notification bell to your header:

```php
// In your header/navigation area
echo $teacherNotificationSystem->renderNotificationBell('Teacher Notifications');
```

## API Endpoints

The `api.php` file provides the following endpoints:

- `GET ?action=get_count` - Get notification count
- `GET ?action=get_notifications&limit=20` - Get all notifications
- `GET ?action=get_by_category&category=student_progress` - Get notifications by category
- `GET ?action=get_stats` - Get notification statistics
- `GET ?action=mark_as_read&id=123` - Mark notification as read

### Categories Available:
- `student_progress`
- `engagement`
- `course_updates`
- `admin_updates`

## Customization

### Adding New Notification Types

1. **Add to the notification class** (`teacher_notifications.php`):
```php
// Add a new method to get your custom notifications
private function getCustomNotifications() {
    $notifications = [];
    
    // Your custom query here
    $stmt = $this->pdo->prepare("SELECT ...");
    // ...
    
    return $notifications;
}

// Include in main getNotifications() method
public function getNotifications($limit = 20) {
    // ... existing code ...
    $notifications = array_merge($notifications, $this->getCustomNotifications());
    // ... existing code ...
}
```

2. **Add icon mapping**:
```php
public function getNotificationIcon($type) {
    $icons = [
        // ... existing icons ...
        'your_custom_type' => '🆕',
    ];
    // ...
}
```

### Styling Customization

Modify `teacher_notifications.css` to change:
- Colors: Update the CSS custom properties
- Sizing: Modify width/height values
- Animation: Adjust transition and animation properties

### JavaScript Customization

The `teacher_notifications.js` provides a `TeacherNotificationManager` class with methods:
- `toggleNotifications()`
- `refreshNotifications()`
- `updateNotificationCount()`
- `getStudentProgressNotifications()`
- `getEngagementAlerts()`
- `getCourseUpdates()`
- `getAdminUpdates()`

## Database Requirements

The system uses existing tables:
- `courses` - Teacher's courses
- `enrollments` - Student enrollments
- `users` - User information
- `quiz_attempts` - Quiz completion data
- `course_progress` - Course completion tracking
- `progress` - Section progress
- `announcement_banner` - System announcements

## Security

- Session-based authentication required
- SQL injection protection via prepared statements
- XSS protection via HTML escaping
- CSRF protection (recommended to add tokens)

## Performance Considerations

- Auto-refresh every 3 minutes (configurable)
- Cached queries with reasonable limits
- Optimized database queries with proper indexes
- Lazy loading of notification details

## Browser Support

- Modern browsers (Chrome 60+, Firefox 55+, Safari 12+)
- Mobile responsive design
- Progressive enhancement (works without JavaScript)

## Troubleshooting

### Common Issues:

1. **Notifications not showing**
   - Check database connections
   - Verify session variables
   - Check browser console for errors

2. **CSS not loading**
   - Verify file paths
   - Check server permissions
   - Clear browser cache

3. **JavaScript errors**
   - Check if jQuery/Alpine.js conflicts
   - Verify API endpoints are accessible
   - Check browser developer tools

### Debug Mode:

Enable PHP error logging to see detailed error messages:
```php
error_log("Teacher notification debug: " . print_r($notifications, true));
```

## Future Enhancements

Planned features:
- Real-time notifications via WebSockets
- Email/SMS notification options
- Notification preferences/settings
- Advanced filtering and search
- Notification history/archive
- Push notifications for mobile

## Example Implementation

See `teacher.php` for a complete implementation example showing how to integrate the notification system into your teacher dashboard.
