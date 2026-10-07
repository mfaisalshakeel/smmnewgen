<?php
/** Contact form. Saves to the messages table, which the admin reads. */

$sent   = false;
$errors = [];
$input  = ['name' => '', 'email' => '', 'whatsapp' => '', 'body' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($input) as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    // Hidden field: a person never sees it, a bot fills it in.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        flash('success', 'Thank you, your message has been sent.');
        redirect('contact');
    }

    if (!rate_limit('contact', 5, 900)) {
        $errors['body'] = 'You have sent several messages already. Please wait a few minutes.';
    }

    if ($input['name'] === '' || mb_strlen($input['name']) > 120) {
        $errors['name'] = 'Please tell us your name.';
    }
    if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address does not look right.';
    }
    if ($input['email'] === '' && $input['whatsapp'] === '') {
        $errors['email'] = 'Give us an email or a WhatsApp number so we can reply.';
    }
    if (mb_strlen($input['body']) < 5) {
        $errors['body'] = 'Please write your message.';
    }
    if (mb_strlen($input['body']) > 4000) {
        $errors['body'] = 'That message is too long.';
    }

    if (!$errors) {
        insert_row('messages', [
            'name'       => $input['name'],
            'email'      => $input['email'],
            'whatsapp'   => $input['whatsapp'],
            'body'       => $input['body'],
            'ip'         => client_ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Thank you, your message has been sent. We usually reply within a few hours.');
        redirect('contact');
    }
}

view('contact', [
    'title'            => 'Contact Us',
    'meta_description' => 'Get in touch with ' . setting('site_name', 'us') . '.',
    'canonical'        => url('contact'),
    'errors'           => $errors,
    'input'            => $input,
]);
