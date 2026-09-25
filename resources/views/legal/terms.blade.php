@include('legal.document', [
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
])
