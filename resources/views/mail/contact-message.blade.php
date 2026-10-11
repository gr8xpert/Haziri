<x-mail::message>
# New enquiry from the website

<x-mail::table>
| | |
|:--|:--|
| **Name** | {{ $contact['name'] }} |
| **Email** | {{ $contact['email'] }} |
| **Phone** | {{ $contact['phone'] ?: '—' }} |
| **Company** | {{ $contact['company'] ?: '—' }} |
| **Employees** | {{ $contact['employees'] ?: '—' }} |
</x-mail::table>

**Message**

{{ $contact['message'] }}

Reply to this email to answer {{ $contact['name'] }} directly.
</x-mail::message>
