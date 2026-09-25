<?php

return [
    'privacy' => [
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
    ],
    'terms' => [
        'pageTitle' => 'Terms and Conditions',
        'eyebrow' => 'Using TouchNRelief',
        'intro' => 'These terms apply when you create an account, schedule an appointment, or use TouchNRelief services.',
        'sections' => [
            ['Account responsibility', 'Provide accurate registration and booking information, keep your login and verification codes private, and notify the spa if you believe your account has been used without permission.'],
            ['Bookings and availability', 'Appointments remain subject to therapist, service, and schedule availability. An appointment awaiting its required initial payment is reserved as payment pending and is confirmed only after the payment is verified.'],
            ['Payments', 'The checkout shows whether full payment or a downpayment is required. Online payments are processed through PayMongo. Any remaining balance must be settled according to the spa policy before a session can begin.'],
            ['Cancellations, lateness, and no shows', 'Cancellation availability follows the cutoff displayed by the system. Late arrivals may reduce service time, and appointments may be marked no show after the allowed grace period. Repeated no shows may result in account restrictions.'],
            ['Service and safety information', 'Customers should provide accurate health and service preference information and tell staff about relevant conditions before treatment. The spa may decline or adjust a service when needed for safety.'],
            ['Acceptable use', 'Do not misuse the system, attempt unauthorized access, interfere with bookings or payments, impersonate another person, or submit false or harmful information.'],
            ['Changes and contact', 'Operational policies may be updated as services change. The current version will remain available on this page. Contact Buenos Touche Spa through the details on the public website with questions.'],
        ],
    ],
    'data_deletion' => [
        'pageTitle' => 'User Data Deletion',
        'eyebrow' => 'Account and social login data',
        'intro' => 'These instructions explain how TouchNRelief customers can request deletion of their account and information received through Google or Facebook login.',
        'sections' => [
            ['Submit a deletion request', 'Contact Buenos Touche Spa using the email address or phone number shown on the public website. State that you want your TouchNRelief account deleted and provide the email address used for the account.'],
            ['Identity confirmation', 'The spa may ask for information needed to confirm that the request belongs to the account owner before deleting or changing account information. Never send your password or social account password.'],
            ['Social login information', 'After a verified deletion request is processed, TouchNRelief removes the connection between the customer account and the Google or Facebook account used to sign in. TouchNRelief does not store social provider access tokens.'],
            ['Information that may be retained', 'Booking, payment, refund, fraud-prevention, and legal business records may be retained when required for legitimate operational or legal purposes. Information that is no longer required will be deleted or anonymized where appropriate.'],
            ['Confirmation', 'The spa will respond to the contact details provided with the request and confirm when the account deletion review has been completed.'],
        ],
    ],
];
