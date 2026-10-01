# AgriBase

AgriBase is a web-based rice and coconut production monitoring and pest decision-support system built with PHP, MySQL, HTML, CSS, and JavaScript.

The system does not require hardware or an external machine-learning dataset.

## Main workflow

```text
Farmer creates account
        ↓
Farmer registers one or more farms
        ↓
Farm status = Pending
        ↓
Administrator validates the farm
        ↓
Approved
        ↓
Farmer + Administrator can monitor the farm
        ↓
Pest / Crop Health Assessments
        ↓
Incidents + Agricultural Analytics
```

## Important role rule

### Farmer

The farmer:

- creates their own account
- creates as many farm profiles as needed
- views each farm
- sees Pending / Approved / Rejected status
- can see the administrator's rejection reason
- can monitor Approved farms
- performs pest checks
- performs crop-health assessments
- submits field reports
- views their incidents and crop history
- reads published agricultural advisories

The farmer cannot approve their own farm.

### Administrator

The administrator:

- monitors farmer accounts
- monitors how many farms each farmer has
- validates Pending farms
- approves or rejects farms
- provides a rejection reason
- monitors Approved farms
- performs administrative assessments
- reviews incidents
- updates incident status
- manages farmer-submitted reports
- manages advisories
- views analytics
- views the real incident risk map
- uses per-user language and appearance preferences

The administrator does NOT create farmer accounts or farmer farms through the application.

The administrator also does NOT create farmer field reports. Reports are created by farmers and managed by administrators.

## Database

### Fresh installation

Import:

```text
database.sql
```

This creates the complete AgriBase database, including farm validation fields.

### Existing installation

If the current `agribase` database already exists and you do not want to delete it, first back it up and then run:

```text
database_update.sql
```

This adds:

- `approval_status`
- `approved_by`
- `approved_at`
- `rejection_reason`
- `updated_at`

Existing farms from the old schema are changed to `Pending` so the administrator can validate them under the new workflow.

## XAMPP

Copy the project to:

```text
C:\xampp\htdocs\agribase
```

Start:

- Apache
- MySQL

Open:

```text
http://localhost/agribase/
```

## Demo accounts

Administrator:

```text
Username: admin
Password: admin123
```

Farmer:

```text
Username: juan
Password: farmer123
```

Another farmer:

```text
Username: maria
Password: farmer123
```

## Email Notifications

AgriBase uses PHPMailer and SMTP for email notifications. Farmer registration requires a six-digit code sent by email before the account is created. Codes expire after 10 minutes, allow five attempts, and are limited to one send per minute and five sends per hour per session.

Newly published advisories are emailed to all registered farmers and administrators. Administrators are also emailed when a farmer submits a farm or report; farmers are emailed when a farm, report, or incident status changes. Missing email configuration does not block saved actions.

When five pest incidents are recorded in the same barangay within seven days, AgriBase emails all registered users an area alert. Pest alerts are sent once when the area first reaches the threshold; email delivery requires the SMTP settings below.

PHPMailer 7.1.1 is bundled under `vendor/phpmailer`. For a local XAMPP setup, copy `includes/notifications.local.php.example` to `includes/notifications.local.php` and fill in the SMTP username, SMTP app password, and sender email. Keep the real settings in `notifications.local.php`; do not commit or share that file. Apache environment variables shown below are also supported and take precedence over local settings:

```apache
SetEnv AGRIBASE_APP_URL https://your-agri-base-domain.example
SetEnv AGRIBASE_SMTP_HOST smtp.gmail.com
SetEnv AGRIBASE_SMTP_PORT 587
SetEnv AGRIBASE_SMTP_USERNAME notifications@example.com
SetEnv AGRIBASE_SMTP_PASSWORD "your-mail-provider-app-password"
SetEnv AGRIBASE_SMTP_ENCRYPTION tls
SetEnv AGRIBASE_SMTP_FROM_EMAIL notifications@example.com
SetEnv AGRIBASE_SMTP_FROM_NAME AgriBase
SetEnv AGRIBASE_CAINFO "C:/path/to/trusted-ca-bundle.pem"
```

Set `AGRIBASE_APP_URL` to the full address where AgriBase is reachable, without a trailing slash. For XAMPP, `http://localhost/agribase` only works on the computer running Apache; to open reset links on another device, use the server computer's reachable LAN address, such as `http://192.168.1.20/agribase`, and allow Apache through the firewall.

`AGRIBASE_CAINFO` is optional on a standard XAMPP/hosting setup. If PHP reports a certificate verification error behind an antivirus or corporate HTTPS inspection proxy, point it to a PEM bundle containing the normal public roots and that trusted inspection root. Keep certificate verification enabled.

For Gmail, enable 2-Step Verification and create an app password; use that app password, not the normal account password. After setup, test registration using an inbox you can access. If delivery fails, check `C:\xampp\apache\logs\error.log` for the provider failure details. Email delivery requires valid SMTP credentials and an allowed sender address.

## Front-end

The project uses:

- HTML
- CSS
- JavaScript
- Leaflet for the real map

Bootstrap is NOT used.

The design uses a top navigation system rather than a sidebar.

## Farm validation

A new farmer-created farm is always:

```text
Pending
```

The administrator opens:

```text
Farmers & Farms
```

then opens a farmer profile.

The administrator can:

```text
Approve Farm
```

or:

```text
Reject Farm
```

A rejection requires a reason.

Only Approved farms appear in:

- Pest Assessment
- Crop Health Assessment
- Farmer New Report
- Monitoring workflows

## No scenario simulation

The previous Scenario Simulation module has been removed from the project workflow.

## Reports

Farmers can create:

```text
New Report
```

Administrators can only:

```text
Review
Update Status
Add Review Notes
View Existing Records
Print Existing Records
```

Administrators do not create farmer reports.

## Security notes

New farmer registrations use PHP `password_hash()`.

The demo accounts in `database.sql` use SHA-256 seed values for easy database setup. When a demo account successfully logs in, the application automatically upgrades its password hash to PHP's secure password format.

For a production deployment, add HTTPS, CSRF protection, stronger session configuration, and a proper password reset workflow.
