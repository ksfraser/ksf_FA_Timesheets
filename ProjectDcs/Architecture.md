# Architecture - ksf_FA_Timesheets

## Document Information
- **Module**: ksf_FA_Timesheets (Timesheets for FrontAccounting)
- **Version**: 1.0.0
- **Date**: 2024-04-26
- **Status**: Planned
- **Author**: KSFII Development Team

## 1. Architecture Overview

### 1.1 Design Principles
The ksf_FA_Timesheets module follows these architectural principles:

1. **Modularity**: Clean separation between UI, business logic, and data layers
2. **Integration**: Bridges ksf_Timesheets core with FA
3. **Compatibility**: WebERP-style functions for FA integration
4. **Type Safety**: PHP 8.0+ features with strict typing

### 1.2 Technology Stack
- **PHP**: 8.0+ with strict typing
- **Database**: MySQL 5.7+ / MariaDB 10.0+
- **Frontend**: Bootstrap 5.x (via FA)
- **Core Library**: ksf_Timesheets

## 2. Directory Structure

```
ksf_FA_Timesheets/
├── FA_Timesheets_Module.php  # Module registration & hooks
├── hooks.php               # Install/activate/deactivate hooks
├── README.md              # Module documentation
├── composer.json          # Package definition
├── ProjectDcs/
│   ├── Architecture.md
│   ├── Functional Requirements.md
│   ├── Test Plan.md
│   ├── UAT Plan.md
│   ├── Business Requirements.md
│   ├── Use Case.md
│   └── RTM.md
```

## 3. Module Components

### 3.1 FA_Timesheets_Module.php
Main module class providing:
- Module metadata
- Permission definitions
- Menu items
- Lifecycle hooks (install, activate, deactivate, uninstall)

**Key Functions**:
```php
function fa_timesheets_get_module_info()    // Returns module metadata
function fa_timesheets_install()           // Creates database tables
function fa_timesheets_activate()           // Activates module
function fa_timesheets_deactivate()        // Deactivates module
function fa_timesheets_uninstall()          // Cleanup on uninstall
function fa_timesheets_get_menu_items()    // Returns navigation menu
```

### 3.2 hooks.php
Handles module lifecycle operations:
- Database installation
- Permission registration
- Menu registration
- Hook registration

### 3.3 Integration Points
The module integrates with:
- ksf_Timesheets library (via composer)
- FA user system
- FA permission system

## 4. Database Schema

### 4.1 Core Table

#### fa_timesheet_entries
```sql
CREATE TABLE fa_timesheet_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    activity_code VARCHAR(10) NOT NULL DEFAULT 'G01',
    project_id INT,
    task_id INT,
    description TEXT,
    status VARCHAR(20) DEFAULT 'Draft',
    approver_id INT,
    approved_date DATETIME,
    rejected_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_date (date),
    INDEX idx_status (status),
    INDEX idx_activity (activity_code)
);
```

### 4.2 Indexes
Key indexes on:
- `employee_id` - Employee lookups
- `date` - Date range queries
- `status` - Status filtering
- `activity_code` - Activity filtering

## 5. Integration Architecture

### 5.1 FrontAccounting Integration
The module integrates with FA through:

1. **Hooks System**: Using FA's hook mechanism
2. **Database Table Prefix**: Using `TB_PREF` constant
3. **Permission System**: Using FA's permission constants
4. **UI Components**: Using FA's form helpers

### 5.2 ksf_Timesheets Integration
The module uses the ksf_Timesheets library:
- TimeEntry entity class
- Status constants
- Activity code definitions

### 5.3 Data Flow
```
User Form → FA_Timesheets_Module → TimeEntry (ksf_Timesheets)
                                        ↓
                              Database (fa_timesheet_entries)
```

## 6. Security Architecture

### 6.1 Input Validation
- All user inputs validated before database operations
- SQL injection prevention via `db_escape()`
- Type casting for numeric inputs

### 6.2 Output Escaping
- HTML output escaped via `htmlspecialchars()`
- JavaScript sanitization for dynamic content

### 6.3 Access Control
- Permission checks on all CRUD operations
- Role-based menu visibility
- Employee can only edit own draft entries
- Manager can only approve team entries

## 7. Workflow Design

### 7.1 Employee Workflow
```
1. Create Time Entry (as Draft)
2. Edit Draft Entry (if needed)
3. Submit for Approval → Status: Submitted
4. Receive notification (future)
5. If Rejected → Edit and Resubmit
```

### 7.2 Manager Workflow
```
1. View Pending Approvals
2. Review Entry Details
3. Approve OR Reject with Reason
4. Employee Notified (future)
```

## 8. Extension Points

### 8.1 Custom Activity Codes
Modules can add custom activity codes via:
- Database reference table
- Configuration settings

### 8.2 Custom Status
Additional statuses can be added:
- Status constants in module
- Valid transitions configured

### 8.3 Notifications
Future notification plugins:
- Email notifications
- In-app notifications
- SMS notifications

## 9. Performance Considerations

### 9.1 Database Indexes
Key indexes on:
- `employee_id` - Employee lookups
- `date` - Date range queries
- `status` - Approval queue filtering

### 9.2 Query Optimization
- Pagination for large datasets
- Efficient JOINs with proper indexes
- Prepared statements for repeated queries

## 10. API Design

### 10.1 Service Layer
```php
class TimesheetsService {
    // Entry operations
    public function createEntry(array $data): TimeEntry
    public function updateEntry(int $id, array $data): TimeEntry
    public function deleteEntry(int $id): bool
    
    // Employee operations
    public function getMyEntries(int $employeeId): array
    public function submitEntry(int $id): bool
    
    // Approver operations
    public function getPendingApprovals(int $approverId): array
    public function approveEntry(int $id, int $approverId): bool
    public function rejectEntry(int $id, int $approverId, string $reason): bool
}
```

### 10.2 TimeEntry Entity
```php
class TimeEntry {
    // Constants
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED = 'Submitted';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';
    
    // Properties
    private ?int $id = null;
    private int $employeeId = 0;
    private string $date = '';
    private float $hours = 0;
    private string $activityCode = 'G01';
    private ?int $projectId = null;
    private ?int $taskId = null;
    private string $description = '';
    private string $status = self::STATUS_DRAFT;
    private ?int $approverId = null;
    private ?string $approvedDate = null;
    
    // Methods
    public function isOvertime(): bool
    public function isApproved(): bool
    public function isSubmitted(): bool
}
```

## 11. Error Handling

### 11.1 Exception Hierarchy
```php
class TimesheetsException extends Exception
class TimesheetsDatabaseException extends TimesheetsException
class TimesheetsValidationException extends TimesheetsException
class TimesheetsPermissionException extends TimesheetsException
```

### 11.2 Error Logging
- Database errors logged with query details
- Validation errors returned to user
- Permission errors logged

## 12. Testing Strategy

### 12.1 Unit Tests
- TimeEntry entity tests
- Status transition tests
- Validation tests

### 12.2 Integration Tests
- FA module integration
- Database operations
- Permission checks

### 12.3 Test Coverage
- CRUD operations
- Workflow transitions
- Approval scenarios

## 13. Deployment

### 13.1 Installation Process
1. Install ksf_Timesheets via composer
2. Copy module files to FA modules directory
3. Install via FA module manager
4. Database tables created automatically
5. Permissions assigned to admin
6. Menu items registered

### 13.2 Upgrade Process
1. Backup database
2. Deactivate module
3. Replace files
4. Activate module
5. Run migration scripts (if any)

### 13.3 Uninstall Process
1. Deactivate module
2. Optionally remove data
3. Delete module files

---
*Document Version: 1.0.0*
*Last Updated: 2024-04-26*
