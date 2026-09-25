@include('legal.document', [
    'pageTitle' => 'Privacy Policy',
    'eyebrow' => 'Your information',
    'intro' => 'This policy explains what TouchNRelief collects, why it is used, and the choices available to customers using the booking system.',
    'sections' => [
        ['Information we collect', 'We collect account details such as your name, username, email address, contact number, birthday, sex, profile details, booking information, service preferences, and payment status. Payment card or wallet credentials are handled by PayMongo and are not stored by TouchNRelief.'],
        ['How information is used', 'Information is used to create and secure your account, arrange appointments, assign therapists, process and reconcile payments, send service messages, support cancellations or refunds, prevent fraud, and maintain business records.'],
        ['Sensitive preferences', 'Health and service preference details are used only to support safe and suitable spa services and are made available only to authorized staff who need them for the appointment workflow.'],
        ['Email and account security', 'Your email may receive verification codes, password recovery codes, booking updates, and appointment reminders. Passwords are stored as secure hashes. Verification codes are temporary and expire automatically.'],
        ['Sharing and service providers', 'Information may be processed by hosting, email delivery, payment, and technical service providers only as needed to operate TouchNRelief. We do not sell customer personal information.'],
        ['Retention and your choices', 'Records are retained for account, booking, payment, security, and legal business needs. You may ask the spa to review or correct your account information, subject to records that must be retained.'],
        ['Contact', 'For privacy questions or account requests, contact Buenos Touche Spa using the phone number or email shown on the public website.'],
    ],
])
