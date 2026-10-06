<?php

namespace App\Support;

/**
 * Single source of truth for Support quick-help and User Manual content. Both guest and
 * admin Support pages render from these arrays, so the admin page can show guest and
 * administrator content without a second copy of the guest articles living anywhere.
 */
class HelpCenterContent
{
    public static function guestHelpCategories(): array
    {
        return [
            [
                'key' => 'getting-started',
                'title' => 'Getting Started',
                'description' => 'What 3YOS Catering is and how to find your way around the site.',
                'articles' => [
                    [
                        'id' => 'about-the-system',
                        'question' => 'What is this website for?',
                        'keywords' => ['about', 'website', 'system', 'online'],
                        'summary' => [
                            'This site lets you browse 3YOS Catering\'s packages and services, send an inquiry, submit a reservation request, and check the status of a reservation you already made — all without creating an account.',
                        ],
                        'manual' => ['chapter' => 'introduction', 'label' => 'Read the Introduction chapter'],
                    ],
                    [
                        'id' => 'using-the-website',
                        'question' => 'How do I use the website?',
                        'keywords' => ['navigate', 'menu', 'website', 'browse'],
                        'summary' => [
                            'Use the top menu to move between Home, About, Services, Packages, Gallery, Status, and Inquiry. "Book an event" starts a reservation request. On mobile, tap the menu icon to open the same links.',
                        ],
                        'manual' => ['chapter' => 'using-the-website', 'label' => 'Read the Using the Website chapter'],
                    ],
                    [
                        'id' => 'contact-3yos',
                        'question' => 'How do I contact 3YOS Catering?',
                        'keywords' => ['contact', 'phone', 'email', 'facebook', 'support'],
                        'summary' => [
                            'Call or text 0998 242 2719, email 3yoscatering@gmail.com, message the 3YOS Catering Facebook page, or use the Inquiry form to send your question directly — whichever is easiest for you.',
                        ],
                        'manual' => ['chapter' => 'contact-support', 'label' => 'Read the Contact / Support chapter'],
                    ],
                ],
            ],
            [
                'key' => 'reservations',
                'title' => 'Reservations',
                'description' => 'Everything about requesting and tracking a reservation.',
                'articles' => [
                    [
                        'id' => 'how-to-make-a-reservation',
                        'question' => 'How do I make a reservation?',
                        'keywords' => ['book', 'reserve', 'reservation form', 'event type', 'package', 'date', 'time'],
                        'summary' => [
                            'Open "Book an event" from the menu and work through the reservation form in order:',
                        ],
                        'steps' => [
                            'Choose your event type (e.g. wedding, birthday, corporate event).',
                            'Pick a package — each one shows its menu, inclusions, and price per head.',
                            'Add any extra services you want, and note special requests if you have them.',
                            'Select your event date, time, and venue. The form tells you right away if a date is already fully booked.',
                            'Enter your contact details and guest count, then review everything on the page before sending it.',
                            'Submit the form.',
                        ],
                        'manual' => ['chapter' => 'making-a-reservation', 'label' => 'Read the Making a Reservation chapter'],
                    ],
                    [
                        'id' => 'after-you-submit',
                        'question' => 'What happens after I submit a reservation?',
                        'keywords' => ['after submission', 'confirmation', 'what next', 'reservation code'],
                        'summary' => [
                            'You\'ll see a confirmation with a unique reservation code, and 3YOS receives your request as Pending. An admin reviews it and either accepts or cancels it — you don\'t need to do anything else while you wait. Keep your reservation code; you\'ll use it to check your status later.',
                        ],
                        'manual' => ['chapter' => 'submitting-a-reservation', 'label' => 'Read the Submitting a Reservation chapter'],
                    ],
                    [
                        'id' => 'checking-status',
                        'question' => 'How do I check my reservation status?',
                        'keywords' => ['status', 'track', 'reservation code', 'lookup'],
                        'summary' => [
                            'Go to "Status" in the menu and enter the reservation code from your confirmation. You\'ll see a timeline — Submitted, Under Review, Accepted, Completed — or a separate Cancelled state if the booking didn\'t move forward.',
                        ],
                        'manual' => ['chapter' => 'checking-reservation-status', 'label' => 'Read the Checking Reservation Status chapter'],
                    ],
                ],
            ],
            [
                'key' => 'payments',
                'title' => 'Payments',
                'description' => 'How payments and the Official Receipt work.',
                'articles' => [
                    [
                        'id' => 'how-payments-work',
                        'question' => 'How do payments work?',
                        'keywords' => ['payment', 'downpayment', 'balance', 'cash'],
                        'summary' => [
                            '3YOS records payments manually — there is no online payment gateway on this site. You arrange payment directly with 3YOS (for example, cash, bank transfer, or GCash), and the admin team logs each payment against your reservation once received.',
                        ],
                        'manual' => ['chapter' => 'payments', 'label' => 'Read the Payments chapter'],
                    ],
                    [
                        'id' => 'payment-status',
                        'question' => 'What do the payment statuses mean?',
                        'keywords' => ['payment status', 'downpayment', 'partial payment', 'fully paid', 'unpaid', 'partially refunded', 'fully refunded'],
                        'summary' => [
                            'No Payment — nothing has been recorded yet.',
                            'Partially Paid — net payments are above zero, but below the contract price.',
                            'Fully Paid — the full contract price has been paid.',
                            'Partially Refunded — a refund reduced the net amount below the contract price.',
                            'Fully Refunded — all recorded payments have been refunded; this is different from No Payment.',
                        ],
                        'manual' => ['chapter' => 'payments', 'label' => 'Read the Payments chapter'],
                    ],
                    [
                        'id' => 'official-receipt',
                        'question' => 'What is an Official Receipt?',
                        'keywords' => ['receipt', 'official receipt', 'proof of payment'],
                        'summary' => [
                            'When 3YOS records a payment you made, they can attach a photo of the receipt to that payment as proof. This is for 3YOS\'s internal record-keeping; if you need a copy of your own receipt, ask 3YOS directly.',
                        ],
                        'manual' => ['chapter' => 'official-receipt', 'label' => 'Read the Official Receipt chapter'],
                    ],
                ],
            ],
            [
                'key' => 'contracts',
                'title' => 'Contracts',
                'description' => 'What the service contract is and what to expect.',
                'articles' => [
                    [
                        'id' => 'what-is-the-contract',
                        'question' => 'What is the service contract?',
                        'keywords' => ['contract', 'service contract', 'agreement'],
                        'summary' => [
                            'For accepted reservations, 3YOS may prepare a service contract covering your event. It\'s uploaded to your reservation as a document (an image of the signed contract) once it\'s ready.',
                        ],
                        'manual' => ['chapter' => 'contracts', 'label' => 'Read the Contracts chapter'],
                    ],
                    [
                        'id' => 'contract-things-to-know',
                        'question' => 'What should I know before signing?',
                        'keywords' => ['contract signing', 'review contract', 'sign'],
                        'summary' => [
                            'Review the package, price, event details, and terms with 3YOS before signing. If anything in the contract doesn\'t match what you agreed on, raise it with 3YOS before confirming — the contract on file is what both sides will refer back to.',
                        ],
                        'manual' => ['chapter' => 'contracts', 'label' => 'Read the Contracts chapter'],
                    ],
                ],
            ],
            [
                'key' => 'faq',
                'title' => 'FAQ',
                'description' => 'Quick answers to common questions.',
                'articles' => [
                    [
                        'id' => 'faq-deposit',
                        'question' => 'Do I need to pay a deposit to reserve a date?',
                        'keywords' => ['deposit', 'downpayment required'],
                        'summary' => ['Submitting a reservation request itself doesn\'t require payment. Ask 3YOS directly about downpayment arrangements once your reservation is accepted.'],
                    ],
                    [
                        'id' => 'faq-change-date',
                        'question' => 'Can I change my event date after submitting?',
                        'keywords' => ['change date', 'reschedule'],
                        'summary' => ['Contact 3YOS directly — reservation details including the date can be updated by the admin team once they\'ve reviewed your request.'],
                    ],
                    [
                        'id' => 'faq-multiple-events',
                        'question' => 'Can two events happen on the same date?',
                        'keywords' => ['same date', 'fully booked', 'availability'],
                        'summary' => ['Yes. A maximum of 4 active reservations/events is allowed per date. Pending and Accepted reservations use capacity; Cancelled and Completed reservations do not. The reservation form will tell you if a date is fully booked.'],
                    ],
                    [
                        'id' => 'faq-cancel',
                        'question' => 'What if I need to cancel?',
                        'keywords' => ['cancel', 'cancellation'],
                        'summary' => ['Contact 3YOS as soon as possible. The admin team updates your reservation status to Cancelled once it\'s confirmed with you.'],
                    ],
                    [
                        'id' => 'faq-confirmed-vs-accepted',
                        'question' => 'What does "Accepted" mean?',
                        'keywords' => ['accepted', 'confirmed reservation'],
                        'summary' => ['"Accepted" means 3YOS has reviewed and approved your reservation request — it\'s the same thing you may see referred to as "Confirmed" in some places.'],
                        'manual' => ['chapter' => 'reservation-statuses', 'label' => 'Read the Reservation Statuses chapter'],
                    ],
                ],
            ],
            [
                'key' => 'contact',
                'title' => 'Contact / Support',
                'description' => 'Ways to reach the 3YOS Catering team.',
                'articles' => [
                    [
                        'id' => 'contact-support-ways',
                        'question' => 'What are my options for getting in touch?',
                        'keywords' => ['support', 'help', 'phone', 'email', 'facebook', 'inquiry form'],
                        'summary' => [
                            'Phone/text: 0998 242 2719',
                            'Email: 3yoscatering@gmail.com',
                            'Facebook: message the 3YOS Catering page',
                            'Inquiry form: tell us about your event and we\'ll reply by email',
                        ],
                        'manual' => ['chapter' => 'contact-support', 'label' => 'Read the Contact / Support chapter'],
                    ],
                ],
            ],
        ];
    }

    public static function adminHelpCategories(): array
    {
        return [
            [
                'key' => 'admin-dashboard',
                'title' => 'Admin Dashboard',
                'description' => 'Your daily operations overview.',
                'articles' => [
                    [
                        'id' => 'dashboard-overview',
                        'question' => 'What am I looking at on the dashboard?',
                        'keywords' => ['dashboard', 'overview', 'needs attention', 'today'],
                        'summary' => [
                            'Needs Attention lists open items that need a decision (pending reservations, inquiries awaiting a reply, accepted bookings with no payment, missing contracts, or an outstanding balance).',
                            'Today shows this week\'s schedule — events today, events in the next 7 days, payments due soon, and inquiries needing a response — each card is clickable and opens the matching filtered list.',
                            'Business overview below that shows lifetime totals, not today\'s activity.',
                        ],
                        'manual' => ['chapter' => 'admin-dashboard', 'label' => 'Read the Admin Dashboard chapter'],
                    ],
                    [
                        'id' => 'needs-attention-detail',
                        'question' => 'How is "Needs Attention" decided?',
                        'keywords' => ['needs attention', 'pending', 'unpaid', 'missing contract'],
                        'summary' => [
                            'It\'s calculated live from your real data, not a manual list: pending reservations, inquiries with status New (or In Progress with no reply sent yet), accepted reservations with no payment on file, accepted reservations missing a contract file, and accepted reservations with a balance still owed.',
                        ],
                        'manual' => ['chapter' => 'needs-attention', 'label' => 'Read the Needs Attention chapter'],
                    ],
                ],
            ],
            [
                'key' => 'admin-reservations',
                'title' => 'Reservations',
                'description' => 'Reviewing, accepting, and managing bookings.',
                'articles' => [
                    [
                        'id' => 'reviewing-reservations',
                        'question' => 'How do I review and accept a reservation?',
                        'keywords' => ['accept', 'pending', 'review reservation', 'confirm'],
                        'summary' => [
                            'Open Reservations from the sidebar. Each row shows the customer, event, date, guests, status, and payment status. Click View to open the full Reservation Detail page, or use the quick Accept/Cancel buttons on pending rows for a fast decision.',
                        ],
                        'steps' => [
                            'Go to Reservations.',
                            'Find the pending booking (or use the status filter).',
                            'Click View to check the full details, or click Accept directly from the list.',
                            'Confirm the action when prompted.',
                        ],
                        'manual' => ['chapter' => 'reservation-status-workflow', 'label' => 'Read the Reservation Status Workflow chapter'],
                    ],
                    [
                        'id' => 'editing-confirmed',
                        'question' => 'Can I edit a reservation after it\'s accepted?',
                        'keywords' => ['edit confirmed reservation', 'change schedule', 'change package'],
                        'summary' => [
                            'Yes. Open the reservation\'s detail page — schedule (date/time/venue/guests), the assigned package, admin notes, and the contract price can all be updated from there, in addition to managing payments and contracts.',
                        ],
                        'manual' => ['chapter' => 'editing-confirmed-reservations', 'label' => 'Read the Editing Confirmed Reservations chapter'],
                    ],
                    [
                        'id' => 'reservation-capacity',
                        'question' => 'How many bookings can be accepted on the same date?',
                        'keywords' => ['capacity', 'fully booked', 'four', 'same date'],
                        'summary' => [
                            'A maximum of 4 active reservations/events is allowed per date. Pending and Accepted reservations use capacity; Cancelled and Completed reservations do not. This limit is enforced on the public reservation form, the admin "Add reservation" form, acceptance actions, and confirmed-reservation date edits — so it can\'t be bypassed from any entry point.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'admin-calendar',
                'title' => 'Calendar',
                'description' => 'Using the reservation calendar on the dashboard.',
                'articles' => [
                    [
                        'id' => 'calendar-usage',
                        'question' => 'How do I use the calendar?',
                        'keywords' => ['calendar', 'schedule', 'month', 'events'],
                        'summary' => [
                            'The calendar on the dashboard shows every pending, accepted, completed, and cancelled event by date, color-coded by status. Hover (or focus) a day with events to preview them, and use the arrows to move between months.',
                        ],
                    ],
                    [
                        'id' => 'calendar-open-reservation',
                        'question' => 'How do I open a reservation from the calendar?',
                        'keywords' => ['calendar click', 'open reservation', 'calendar event'],
                        'summary' => [
                            'Click any event — in the calendar grid or in the reservation cards listed below it — to go straight to that reservation\'s detail page. Multiple events on the same date, including cancelled ones, each open their own correct reservation.',
                        ],
                        'manual' => ['chapter' => 'calendar', 'label' => 'Read the Calendar chapter'],
                    ],
                ],
            ],
            [
                'key' => 'admin-payments',
                'title' => 'Payments',
                'description' => 'Recording payments and tracking balances.',
                'articles' => [
                    [
                        'id' => 'recording-a-payment',
                        'question' => 'How do I record a payment?',
                        'keywords' => ['record payment', 'add payment', 'downpayment', 'receipt'],
                        'summary' => ['Payments are entered manually against a reservation\'s contract price — there is no online payment processing.'],
                        'steps' => [
                            'Open the reservation.',
                            'Go to its Payments section.',
                            'Click Add Payment.',
                            'Enter the date, amount, payment method, and type.',
                            'Attach an Official Receipt image if you have one — optional.',
                            'Save.',
                        ],
                        'manual' => ['chapter' => 'payment-management', 'label' => 'Read the Payment Management chapter'],
                    ],
                    [
                        'id' => 'payment-history-balance',
                        'question' => 'Where do I see payment history and balance?',
                        'keywords' => ['payment history', 'balance', 'remaining balance'],
                        'summary' => [
                            'Each reservation\'s Payments page lists every payment and refund in order, with the running balance, and shows who recorded each entry. The reservation detail page summarizes the same totals (contract price, paid, refunded, balance, payment status).',
                        ],
                    ],
                    [
                        'id' => 'refunds',
                        'question' => 'How do refunds work?',
                        'keywords' => ['refund', 'partial refund', 'full refund'],
                        'summary' => [
                            'Refunds are recorded the same way as payments, against the reservation\'s Payments page, and reduce the net amount paid without changing the reservation\'s status. Partial and full refunds are both supported, and a refund can never exceed what was actually paid.',
                        ],
                        'manual' => ['chapter' => 'payment-management', 'label' => 'Read the Payment Management chapter'],
                    ],
                ],
            ],
            [
                'key' => 'admin-receipts',
                'title' => 'Official Receipts',
                'description' => 'Uploading and viewing receipt images.',
                'articles' => [
                    [
                        'id' => 'uploading-receipt',
                        'question' => 'How do I upload an Official Receipt?',
                        'keywords' => ['upload receipt', 'official receipt'],
                        'summary' => ['When adding or editing a payment, attach the receipt image (JPG, PNG, or WEBP, up to 5 MB). It\'s optional — a payment can be saved without one.'],
                        'manual' => ['chapter' => 'official-receipt-management', 'label' => 'Read the Official Receipt Management chapter'],
                    ],
                    [
                        'id' => 'viewing-receipt',
                        'question' => 'How do I view or replace a receipt?',
                        'keywords' => ['view receipt', 'replace receipt'],
                        'summary' => ['Open the payment\'s receipt from the Payments page to preview it. Editing that payment lets you replace the image, and deleting the payment removes the receipt with it.'],
                    ],
                ],
            ],
            [
                'key' => 'admin-contracts',
                'title' => 'Contracts',
                'description' => 'Uploading and managing service contracts.',
                'articles' => [
                    [
                        'id' => 'uploading-contract',
                        'question' => 'How do I upload a contract?',
                        'keywords' => ['upload contract', 'service contract'],
                        'summary' => ['From the reservation\'s detail page, use the Contract section\'s upload form to attach a signed contract image (JPG, PNG, or WEBP, up to 5 MB). You can upload more than one file to the same reservation.'],
                        'manual' => ['chapter' => 'contract-management', 'label' => 'Read the Contract Management chapter'],
                    ],
                    [
                        'id' => 'replacing-contract',
                        'question' => 'How do I replace or remove a contract?',
                        'keywords' => ['replace contract', 'delete contract'],
                        'summary' => ['Upload the corrected file as a new contract, then delete the outdated one from the same section. There\'s no separate "replace" action — it\'s upload-then-delete.'],
                    ],
                    [
                        'id' => 'contract-status',
                        'question' => 'Is there a contract status I need to set?',
                        'keywords' => ['contract status'],
                        'summary' => ['No separate status field exists for contracts — a reservation either has an uploaded file or it doesn\'t. The dashboard\'s Needs Attention flags accepted reservations with no contract on file yet.'],
                    ],
                ],
            ],
            [
                'key' => 'admin-inquiries',
                'title' => 'Inquiries',
                'description' => 'Responding to customer questions.',
                'articles' => [
                    [
                        'id' => 'responding-to-inquiries',
                        'question' => 'How do I respond to an inquiry?',
                        'keywords' => ['reply', 'respond', 'inquiry'],
                        'summary' => ['Open Inquiries, find one in Needs Attention or the full list, and open it to see the customer\'s message.'],
                        'steps' => [
                            'Open the inquiry.',
                            'Type your reply in the response box.',
                            'Click Send.',
                        ],
                        'manual' => ['chapter' => 'inquiry-management', 'label' => 'Read the Inquiry Management chapter'],
                    ],
                    [
                        'id' => 'inquiry-status-changes',
                        'question' => 'How does an inquiry\'s status change?',
                        'keywords' => ['inquiry status', 'new', 'in progress', 'responded'],
                        'summary' => [
                            'Sending a reply automatically emails the customer, saves your response, and moves the status to Responded — there\'s no separate step. Viewing an inquiry never changes its status by itself; a status only moves from New to In Progress if a reply attempt fails to send (so you don\'t lose track of it).',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'admin-packages-services',
                'title' => 'Packages & Services',
                'description' => 'Managing what customers can book.',
                'articles' => [
                    [
                        'id' => 'managing-packages',
                        'question' => 'How do I add or edit a package?',
                        'keywords' => ['package', 'edit package', 'add package'],
                        'summary' => ['Primary Admins only: open Packages, then Add Package or edit an existing one — name, price, menu, inclusions, add-ons, and photo. Saving a change asks you to confirm your admin password first.'],
                        'manual' => ['chapter' => 'package-management', 'label' => 'Read the Package Management chapter'],
                    ],
                    [
                        'id' => 'managing-services',
                        'question' => 'How do I manage services?',
                        'keywords' => ['service', 'add-on', 'toggle service'],
                        'summary' => ['Primary Admins only: open Services to add, edit, or toggle a service on/off. Disabled services stop showing to guests but aren\'t deleted.'],
                        'manual' => ['chapter' => 'service-management', 'label' => 'Read the Service Management chapter'],
                    ],
                ],
            ],
            [
                'key' => 'admin-gallery',
                'title' => 'Gallery',
                'description' => 'Managing the public photo gallery.',
                'articles' => [
                    [
                        'id' => 'managing-gallery',
                        'question' => 'How do I add or remove a gallery photo?',
                        'keywords' => ['gallery', 'photo', 'upload image'],
                        'summary' => ['Primary Admins only: open Gallery, upload an image, and mark it featured if it should stand out. Deleting a photo removes it from the public gallery immediately.'],
                        'manual' => ['chapter' => 'gallery-management', 'label' => 'Read the Gallery Management chapter'],
                    ],
                ],
            ],
            [
                'key' => 'admin-reports',
                'title' => 'Reports & Analytics',
                'description' => 'Business performance and exports.',
                'articles' => [
                    [
                        'id' => 'viewing-reports',
                        'question' => 'Where do I download reports?',
                        'keywords' => ['reports', 'export', 'csv', 'excel'],
                        'summary' => ['Primary Admins only: Reports shows Daily, Weekly, Monthly, and Yearly summaries, each downloadable as CSV or Excel.'],
                        'manual' => ['chapter' => 'reports-and-analytics', 'label' => 'Read the Reports and Analytics chapter'],
                    ],
                    [
                        'id' => 'viewing-analytics',
                        'question' => 'What does the Analytics page show?',
                        'keywords' => ['analytics', 'business metrics', 'trends', 'top packages'],
                        'summary' => ['Primary Admins only: Analytics shows overall totals, a breakdown by reservation status, your top-performing packages, a monthly trend, and the most recent reservations, payments, and refunds.'],
                    ],
                ],
            ],
            [
                'key' => 'admin-activity',
                'title' => 'Activity / Audit Log',
                'description' => 'Reviewing what changed and who changed it.',
                'articles' => [
                    [
                        'id' => 'viewing-activity',
                        'question' => 'Where do I see a history of admin actions?',
                        'keywords' => ['activity log', 'audit', 'history'],
                        'summary' => ['Primary Admins only: Activity logs lists actions like status updates, payments and refunds recorded, inquiry replies, and package/gallery/backup changes — each with who did it, when, and from where.'],
                        'manual' => ['chapter' => 'activity-audit-log', 'label' => 'Read the Activity/Audit Log chapter'],
                    ],
                    [
                        'id' => 'reservation-change-history',
                        'question' => 'Can I see what changed on a specific reservation?',
                        'keywords' => ['reservation history', 'activity', 'what changed'],
                        'summary' => ['Yes — the Reservation Detail page has its own Activity section showing recent log entries matched to that reservation, such as acceptance, cancellation, automatic completion, or payments recorded against it.'],
                    ],
                ],
            ],
            [
                'key' => 'admin-account',
                'title' => 'Account / Security',
                'description' => 'Admin accounts and password security.',
                'articles' => [
                    [
                        'id' => 'admin-login',
                        'question' => 'How do I log in as an admin?',
                        'keywords' => ['admin login', 'sign in'],
                        'summary' => ['Go to /admin/login and sign in with your admin email and password. If you\'ve forgotten your password, use the "Forgot password" link on that page.'],
                        'manual' => ['chapter' => 'security-and-account-management', 'label' => 'Read the Security and Account Management chapter'],
                    ],
                    [
                        'id' => 'admin-roles',
                        'question' => 'What\'s the difference between admin roles?',
                        'keywords' => ['admin role', 'primary admin', 'team admin'],
                        'summary' => ['Every Team Admin can manage reservations and inquiries. Primary Admins additionally manage packages, services, gallery, reports, analytics, activity logs, Team Admin accounts, and backups.'],
                    ],
                    [
                        'id' => 'password-confirmation',
                        'question' => 'Why am I asked for my password again on some actions?',
                        'keywords' => ['confirm password', 're-enter password'],
                        'summary' => ['Sensitive changes — like editing or deleting a package, service, or gallery item — ask you to re-enter your admin password first, as an extra safeguard against accidental or unauthorized changes.'],
                    ],
                ],
            ],
        ];
    }

    public static function guestManualChapters(): array
    {
        return [
            [
                'key' => 'introduction',
                'number' => 1,
                'title' => 'Introduction',
                'intro' => ['This manual explains how to use the 3YOS Catering website as a guest — browsing what\'s offered, requesting a reservation, and tracking it afterward. No account or login is required for any of this.'],
                'sections' => [],
            ],
            [
                'key' => 'getting-started',
                'number' => 2,
                'title' => 'Getting Started',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What you can do here', 'body' => ['Browse packages and services, view the photo gallery, send an inquiry, request a reservation, and check an existing reservation\'s status.']],
                ],
                'related' => ['category' => 'getting-started'],
            ],
            [
                'key' => 'using-the-website',
                'number' => 3,
                'title' => 'Using the Website',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Main navigation', 'body' => ['Home, About, Services, Packages, Gallery, Status, and Inquiry are always available from the top menu, with "Book an event" as the main call to action. On a phone, tap the menu icon to expand the same links.']],
                ],
                'related' => ['category' => 'getting-started'],
            ],
            [
                'key' => 'packages-and-services',
                'number' => 4,
                'title' => 'Packages and Services',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Choosing a package', 'body' => ['The Packages page lists each package with its price per head, menu, and inclusions. Open a package to see its full details before you reserve.']],
                    ['heading' => 'Adding services', 'body' => ['Extra services can be added to your reservation in the reservation form itself — they aren\'t booked separately.']],
                ],
            ],
            [
                'key' => 'making-a-reservation',
                'number' => 5,
                'title' => 'Making a Reservation',
                'intro' => ['The reservation form walks you through everything 3YOS needs to plan your event.'],
                'sections' => [
                    ['heading' => 'Event type', 'body' => ['Pick the kind of event you\'re planning — this helps 3YOS tailor the recommendation.']],
                    ['heading' => 'Package', 'body' => ['Select the package that fits your guest count and budget.']],
                    ['heading' => 'Services', 'body' => ['Add any extra services and note special requests.']],
                    ['heading' => 'Date, time, and venue', 'body' => ['The form checks availability as you choose a date. The maximum is 4 active reservations/events per date. Pending and Accepted reservations both use capacity; a date at capacity is shown as fully booked and can\'t be selected. Cancelled and Completed reservations do not use capacity.']],
                    ['heading' => 'Your details', 'body' => ['Enter your name, contact number, email, address, and guest count.']],
                ],
                'related' => ['category' => 'reservations'],
            ],
            [
                'key' => 'reviewing-a-reservation',
                'number' => 6,
                'title' => 'Reviewing a Reservation',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Before you submit', 'body' => ['Check every section of the form for accuracy — event type, package, date/time/venue, guest count, and contact details — since this is what 3YOS will use to plan and confirm your event.']],
                ],
            ],
            [
                'key' => 'submitting-a-reservation',
                'number' => 7,
                'title' => 'Submitting a Reservation',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What happens on submit', 'body' => ['Your request is saved with status Pending and you\'re given a unique reservation code. 3YOS reviews it and updates the status — there\'s nothing further for you to submit at this stage.']],
                ],
                'related' => ['category' => 'reservations'],
            ],
            [
                'key' => 'checking-reservation-status',
                'number' => 8,
                'title' => 'Checking Reservation Status',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Looking up your reservation', 'body' => ['Go to Status in the menu and enter your reservation code. You\'ll see a progress timeline for your event.']],
                ],
                'related' => ['category' => 'reservations'],
            ],
            [
                'key' => 'reservation-statuses',
                'number' => 9,
                'title' => 'Reservation Statuses',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What each status means', 'body' => [], 'list' => [
                        'Submitted / Pending — received, awaiting review.',
                        'Under Review — 3YOS is reviewing your request.',
                        'Accepted — your event is confirmed to proceed.',
                        'Completed — the event has taken place.',
                        'Cancelled — the booking did not move forward; shown as its own timeline rather than a step in the main one.',
                    ]],
                ],
            ],
            [
                'key' => 'payments',
                'number' => 10,
                'title' => 'Payments',
                'intro' => [],
                'sections' => [
                    ['heading' => 'How payment is handled', 'body' => ['Payments are arranged directly with 3YOS and recorded manually on their end — there is no online checkout on this site.']],
                    ['heading' => 'Payment status', 'body' => [], 'list' => ['No Payment', 'Partially Paid', 'Fully Paid', 'Partially Refunded', 'Fully Refunded']],
                ],
                'related' => ['category' => 'payments'],
            ],
            [
                'key' => 'official-receipt',
                'number' => 11,
                'title' => 'Official Receipt',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What it is', 'body' => ['When 3YOS logs a payment you made, they may attach a photo of the receipt as their internal proof of payment. Keep your own copy of any receipt you\'re given.']],
                ],
                'related' => ['category' => 'payments'],
            ],
            [
                'key' => 'contracts',
                'number' => 12,
                'title' => 'Contracts',
                'intro' => [],
                'sections' => [
                    ['heading' => 'The service contract', 'body' => ['For accepted reservations, 3YOS may prepare a service contract. Review the package, price, event details, and terms before signing, and raise any discrepancy before confirming.']],
                ],
                'related' => ['category' => 'contracts'],
            ],
            [
                'key' => 'faq',
                'number' => 13,
                'title' => 'FAQ',
                'intro' => ['See the FAQ category in Support Quick Help for answers to common questions about deposits, rescheduling, multiple events on one date, and cancellations.'],
                'sections' => [],
                'related' => ['category' => 'faq'],
            ],
            [
                'key' => 'contact-support',
                'number' => 14,
                'title' => 'Contact / Support',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Reach 3YOS Catering', 'body' => [], 'list' => [
                        'Phone/text: 0998 242 2719',
                        'Email: 3yoscatering@gmail.com',
                        'Facebook: 3YOS Catering page',
                        'Inquiry form on this site',
                    ]],
                ],
                'related' => ['category' => 'contact'],
            ],
        ];
    }

    public static function adminManualChapters(): array
    {
        return [
            [
                'key' => 'introduction',
                'number' => 1,
                'title' => 'Introduction',
                'intro' => ['This manual is the complete reference for administering 3YOS Catering\'s operations workspace: reservations, payments, contracts, inquiries, content, and reporting.'],
                'sections' => [],
            ],
            [
                'key' => 'admin-getting-started',
                'number' => 2,
                'title' => 'Admin Getting Started',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Signing in', 'body' => ['Go to /admin/login and sign in with your admin email and password.']],
                    ['heading' => 'Finding your way around', 'body' => ['The sidebar groups Workspace (Overview, Reservations, Inquiries — available to Primary Admins and Team Admins) from Content & Insights and System, which are available to Primary Admins.']],
                ],
            ],
            [
                'key' => 'admin-dashboard',
                'number' => 3,
                'title' => 'Admin Dashboard',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Needs Attention', 'body' => ['Open items that need a decision, computed live from current data — see the Needs Attention chapter.']],
                    ['heading' => 'Today', 'body' => ['This week\'s schedule, not lifetime totals: events today, events in the next 7 days, payments due soon, and inquiries needing a response. Each card is clickable and opens the matching filtered reservation or inquiry list — so the number on the card and the records behind it always match.']],
                    ['heading' => 'Business overview and calendar', 'body' => ['Lifetime totals (bookings, revenue, outstanding balance, completed events) sit below Today, followed by the reservation calendar. Dates with reservations show active capacity from Pending and Accepted events; Cancelled and Completed history remains visible without using a slot.']],
                ],
                'related' => ['category' => 'admin-dashboard'],
            ],
            [
                'key' => 'needs-attention',
                'number' => 4,
                'title' => 'Needs Attention',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What counts as needing attention', 'body' => [], 'list' => [
                        'Pending reservations awaiting a decision.',
                        'Inquiries with status New, or In Progress with no reply sent yet.',
                        'Confirmed reservations still in progress are considered accepted and actionable; completed and cancelled reservations are excluded.',
                        'No payment is determined from the live payment and refund ledger (or the legacy opening balance when no ledger exists). A zero net amount paid is treated as unpaid.',
                        'A missing contract is determined from the reservation’s current contract documents, including legacy and current stored contract files.',
                        'An outstanding balance requires a contract price and a valid calculated balance greater than zero. Reservations with no contract price or invalid payment/refund history are not counted.',
                    ]],
                ],
                'related' => ['category' => 'admin-dashboard'],
            ],
            [
                'key' => 'reservation-management',
                'number' => 5,
                'title' => 'Reservation Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'The reservations list', 'body' => ['Shows ID, customer, event, date, guests, status, and payment status for every reservation, with filters for status, payment status, date range, and a text search. Pending rows get quick Accept/Cancel buttons; everything else opens from View.']],
                    ['heading' => 'Adding a reservation manually', 'body' => ['Full staff can also create a reservation directly from Add reservation, subject to the same 4-per-date acceptance limit as the public form.']],
                ],
                'related' => ['category' => 'admin-reservations'],
            ],
            [
                'key' => 'reservation-detail',
                'number' => 6,
                'title' => 'Reservation Detail',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What\'s on the page', 'body' => [], 'list' => [
                        'Customer — name, phone, email, address as submitted.',
                        'Event — type, date, time, venue, guest count.',
                        'Package — assigned package, add-ons, special requests, contract amount.',
                        'Status — current status, the accept/complete/cancel actions, and a timeline view.',
                        'Contract — upload form and the list of files on file, with view/delete.',
                        'Payment — contract price, paid, refunded, balance, and payment status, linking to the full payment history.',
                        'Activity — recent log entries matched to this reservation.',
                    ]],
                ],
                'related' => ['category' => 'admin-reservations'],
            ],
            [
                'key' => 'reservation-status-workflow',
                'number' => 7,
                'title' => 'Reservation Status Workflow',
                'intro' => [],
                'sections' => [
                    ['heading' => 'The four statuses', 'body' => [], 'list' => [
                        'Pending — newly submitted, awaiting review.',
                        'Confirmed (shown to guests and in most of the admin UI as "Accepted") — reviewed and approved.',
                        'Completed — the event has taken place.',
                        'Cancelled — the booking will not proceed.',
                    ]],
                    ['heading' => 'Accepting or cancelling a booking', 'body' => ['Use the dedicated Accept or Cancel action; reservation status cannot be changed through a manual status selector. Pending reservations already use capacity. A pending reservation can be accepted without using an additional slot, while acceptance is blocked if 4 other active reservations occupy the event date. Cancelling a pending or accepted reservation releases its slot. These workflow rules are enforced on the server.']],
                    ['heading' => 'Automatic completion', 'body' => ['Accepted reservations are automatically marked Completed at 11:59 PM on the event date in the application timezone. Completion is system-controlled and is not a manual reservation action.']],
                ],
                'related' => ['category' => 'admin-reservations'],
            ],
            [
                'key' => 'editing-confirmed-reservations',
                'number' => 8,
                'title' => 'Editing Confirmed Reservations',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What can change after acceptance', 'body' => ['Schedule (date, time, venue, guest count), the assigned package, admin notes, and the contract price can all be edited from the reservation detail page after a booking is accepted — along with ongoing payment and contract management.']],
                ],
                'related' => ['category' => 'admin-reservations'],
            ],
            [
                'key' => 'calendar',
                'number' => 9,
                'title' => 'Calendar',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Reading the calendar', 'body' => ['Shown on the dashboard, color-coded by status (pending, accepted, completed, cancelled), with multiple events per date supported — same-date bookings are never merged or treated as a conflict. Dates with reservations also show active capacity (Pending plus Accepted); Cancelled and Completed events remain visible without using a slot.']],
                    ['heading' => 'Opening a reservation from the calendar', 'body' => ['Every event — in the grid and in the reservation cards listed below it for the selected month — links to that exact reservation\'s detail page, including cancelled events, so you can review their history.']],
                ],
                'related' => ['category' => 'admin-calendar'],
            ],
            [
                'key' => 'customer-management',
                'number' => 10,
                'title' => 'Customer Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Where customer info lives', 'body' => ['Customer details are captured per reservation at submission time. A repeat customer\'s past reservations are not automatically merged into a single profile view — look them up by name, email, or phone using the reservation search.']],
                ],
            ],
            [
                'key' => 'payment-management',
                'number' => 11,
                'title' => 'Payment Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Recording a payment', 'body' => [], 'list' => ['Open the reservation.', 'Go to Payments.', 'Add Payment: date, amount, method, type, and an optional receipt image.', 'Save.']],
                    ['heading' => 'Balance and status', 'body' => ['Payment status (No Payment, Partially Paid, Fully Paid, Partially Refunded, Fully Refunded) and the remaining balance are calculated automatically from the payment and refund history — they\'re never edited directly.']],
                    ['heading' => 'Refunds', 'body' => ['Recorded the same way, against the same page. A refund can\'t exceed what was actually paid, and refunding a reservation never changes its status by itself.']],
                    ['heading' => 'Troubleshooting', 'body' => ['A payment can\'t be saved for more than the remaining balance, and the contract price can\'t be lowered below what\'s already been paid — both are deliberate guardrails, not bugs.']],
                ],
                'related' => ['category' => 'admin-payments'],
            ],
            [
                'key' => 'official-receipt-management',
                'number' => 12,
                'title' => 'Official Receipt Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Uploading, viewing, and replacing', 'body' => ['Attach a receipt image (JPG/PNG/WEBP, up to 5 MB) when adding or editing a payment — it\'s optional. View it from the Payments page, and replace it by editing that payment with a new file. Deleting the payment removes its receipt.']],
                ],
                'related' => ['category' => 'admin-receipts'],
            ],
            [
                'key' => 'contract-management',
                'number' => 13,
                'title' => 'Contract Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Uploading and removing contracts', 'body' => ['Upload signed contract images (JPG/PNG/WEBP, up to 5 MB) from the reservation detail page; more than one file can be attached. To replace one, upload the new file then delete the old one.']],
                    ['heading' => 'Contract status', 'body' => ['There is no separate status field — presence or absence of an uploaded file is the signal. Needs Attention flags accepted reservations with none on file.']],
                ],
                'related' => ['category' => 'admin-contracts'],
            ],
            [
                'key' => 'inquiry-management',
                'number' => 14,
                'title' => 'Inquiry Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'The inbox', 'body' => ['Needs Attention at the top lists inquiries with status New, or In Progress with no reply yet, newest first. Below it, the full list supports filtering by status and searching by name, email, subject, or inquiry ID.']],
                    ['heading' => 'Responding', 'body' => ['Open an inquiry, type a reply, and click Send. That single action emails the customer, saves your response with a timestamp, and sets status to Responded — there\'s no separate status step.']],
                    ['heading' => 'What viewing does and doesn\'t do', 'body' => ['Opening an inquiry marks it read (removing the unread indicator) but never changes its status by itself.']],
                ],
                'related' => ['category' => 'admin-inquiries'],
            ],
            [
                'key' => 'package-management',
                'number' => 15,
                'title' => 'Package Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Who can manage packages', 'body' => ['Primary Admins only. Open Packages to add, edit, or remove one — name, price, menu, inclusions, add-ons, and photo.']],
                    ['heading' => 'Password confirmation', 'body' => ['Saving, updating, or deleting a package asks you to re-enter your admin password first.']],
                ],
                'related' => ['category' => 'admin-packages-services'],
            ],
            [
                'key' => 'service-management',
                'number' => 16,
                'title' => 'Service Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Managing services', 'body' => ['Primary Admins only. Add, edit, or toggle a service on/off from Services; a disabled service stops appearing to guests without being deleted. Changes also require your admin password.']],
                ],
                'related' => ['category' => 'admin-packages-services'],
            ],
            [
                'key' => 'gallery-management',
                'number' => 17,
                'title' => 'Gallery Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Managing photos', 'body' => ['Primary Admins only. Upload images to Gallery, mark one featured to highlight it, and delete to remove it from the public page immediately. Changes require your admin password.']],
                ],
                'related' => ['category' => 'admin-gallery'],
            ],
            [
                'key' => 'reports-and-analytics',
                'number' => 18,
                'title' => 'Reports and Analytics',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Reports', 'body' => ['Primary Admins only. Daily, Weekly, Monthly, and Yearly summaries, each exportable as CSV or Excel.']],
                    ['heading' => 'Analytics', 'body' => ['Primary Admins only. Lifetime totals, a breakdown by reservation status, top-performing packages, a monthly trend, and recent reservations, payments, and refunds.']],
                ],
                'related' => ['category' => 'admin-reports'],
            ],
            [
                'key' => 'activity-audit-log',
                'number' => 19,
                'title' => 'Activity/Audit Log',
                'intro' => [],
                'sections' => [
                    ['heading' => 'What gets logged', 'body' => ['Primary Admins only. Reservation acceptance, cancellation, and automatic completion; payments and refunds recorded; inquiry replies; and package/service/gallery/backup changes, each with the actor, timestamp, and IP address.']],
                    ['heading' => 'Reservation-level activity', 'body' => ['A reservation\'s own detail page shows the log entries matched to it, so you can trace what changed on that specific booking.']],
                ],
                'related' => ['category' => 'admin-activity'],
            ],
            [
                'key' => 'security-and-account-management',
                'number' => 20,
                'title' => 'Security and Account Management',
                'intro' => [],
                'sections' => [
                    ['heading' => 'Admin roles', 'body' => ['Every Team Admin manages reservations and inquiries. Primary Admins additionally manage packages, services, gallery, reports, analytics, activity logs, Team Admin accounts, and backups.']],
                    ['heading' => 'Administrator passwords', 'body' => ['New Admin passwords must be at least 12 characters and include uppercase and lowercase letters, a number, and a symbol. Password confirmation is required.']],
                    ['heading' => 'Password confirmation', 'body' => ['Sensitive content changes ask for your password again before saving, as a safeguard beyond just being logged in.']],
                    ['heading' => 'Team Admins and backups', 'body' => ['Primary Admins can add Team Admin accounts and reset their passwords, and manage full database backups — create, download, restore, or delete — from the System section of the sidebar.']],
                ],
                'related' => ['category' => 'admin-account'],
            ],
            [
                'key' => 'troubleshooting',
                'number' => 21,
                'title' => 'Troubleshooting',
                'intro' => [],
                'sections' => [
                    ['heading' => '"Accept" is unavailable for a pending reservation', 'body' => ['The event date already has 4 other active reservations (Pending or Accepted) — this is the capacity limit, not an error.']],
                    ['heading' => 'A payment won\'t save', 'body' => ['Check that the amount doesn\'t exceed the remaining balance, and that a contract price has been set on the reservation.']],
                    ['heading' => 'A save asks for my password again', 'body' => ['Expected behavior for package, service, and gallery changes — re-enter your admin password to continue.']],
                    ['heading' => 'A page redirects me to the login screen', 'body' => ['Your admin session has ended; sign in again.']],
                ],
            ],
            [
                'key' => 'faq',
                'number' => 22,
                'title' => 'FAQ',
                'intro' => ['See the FAQ-style questions throughout Administrator Quick Help for answers on roles, capacity, contract status, and inquiry status — this chapter is the companion reference, not a duplicate list.'],
                'sections' => [],
            ],
        ];
    }
}
