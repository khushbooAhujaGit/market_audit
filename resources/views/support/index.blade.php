<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support · Market Audit</title>
    <link rel="icon" href="{{ url('assets/images/favicontnbt.jpeg') }}" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ url('assets/css/font-awesome.css') }}">
    <style>
        :root {
            --navy: #0d2c3c;
            --navy-hover: #154257;
            --accent: #1f7fb6;
            --ink: #1c2a33;
            --muted: #5f6f7a;
            --line: #dbe3e8;
            --field: #f4f7f9;
            --page: #f7f9fb;
            --card: #ffffff;
            --ok: #1e7a45;
            --ok-bg: #e4f4ea;
            --err: #b42318;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--ink); font-family: "Rubik", "Segoe UI", Arial, sans-serif; font-size: 15px; line-height: 1.55; }
        .wrap { min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center;
                max-width: 1200px; margin: 0 auto; padding: 40px max(16px, env(safe-area-inset-right, 0px)) 40px max(16px, env(safe-area-inset-left, 0px)); }
        .intro { display: flex; flex-direction: column; gap: 22px; max-width: 470px; }
        .intro .logo { width: 150px; height: auto; }
        .intro h1 { margin: 0; font-size: 34px; line-height: 1.2; font-weight: 700; color: var(--navy); }
        .intro p { margin: 0; color: var(--muted); font-size: 16px; }
        .points { display: flex; flex-direction: column; gap: 14px; margin: 0; padding: 0; list-style: none; }
        .points li { display: grid; grid-template-columns: 42px 1fr; gap: 12px; align-items: start; }
        .points .icon { width: 42px; height: 42px; border-radius: 10px; border: 1px solid var(--line); background: var(--card);
                        display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 16px; }
        .points b { display: block; font-weight: 600; color: var(--ink); }
        .points span { color: var(--muted); font-size: 14px; }
        .brand-mark { width: min(320px, 70%); opacity: .9; margin-top: 8px; }
        .back { color: var(--accent); text-decoration: none; font-weight: 500; font-size: 14px; }
        .back:hover { text-decoration: underline; }

        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 34px 32px 30px;
                box-shadow: 0 18px 40px -24px rgba(13, 44, 60, .35); display: flex; flex-direction: column; gap: 18px; }
        .card-head { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .card-head img { width: 86px; height: auto; }
        .card-head h2 { margin: 0; font-size: 22px; font-weight: 700; color: var(--navy); }
        form { display: flex; flex-direction: column; gap: 16px; }
        label { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: var(--ink); }
        label small { font-weight: 400; color: var(--muted); }
        .field { display: flex; align-items: stretch; border: 1px solid var(--line); border-radius: 10px; background: var(--card); overflow: hidden; }
        .field:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(31, 127, 182, .15); }
        .field .addon { width: 48px; display: flex; align-items: center; justify-content: center; background: var(--field); color: var(--navy); border-right: 1px solid var(--line); flex: none; }
        .field input, textarea { flex: 1; min-width: 0; border: 0; outline: none; padding: 13px 14px; font: inherit; color: var(--ink); background: transparent; }
        textarea { width: 100%; min-height: 120px; resize: vertical; border: 1px solid var(--line); border-radius: 10px; }
        textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(31, 127, 182, .15); }
        .invalid .field, textarea.invalid { border-color: var(--err); }
        .error { color: var(--err); font-size: 13px; margin-top: 5px; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .counter { text-align: right; font-size: 12px; color: var(--muted); margin-top: 4px; font-variant-numeric: tabular-nums; }
        button { border: 0; border-radius: 10px; background: var(--navy); color: #fff; font: inherit; font-weight: 600; font-size: 16px;
                 padding: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        button:hover { background: var(--navy-hover); }
        button:focus-visible, .back:focus-visible { outline: 3px solid rgba(31, 127, 182, .45); outline-offset: 2px; }
        button[disabled] { opacity: .7; cursor: default; }

        .sent { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 10px 0; }
        .sent .tick { width: 60px; height: 60px; border-radius: 50%; background: var(--ok-bg); color: var(--ok); display: flex; align-items: center; justify-content: center; font-size: 26px; }
        .sent h3 { margin: 0; font-size: 20px; color: var(--navy); }
        .sent p { margin: 0; color: var(--muted); max-width: 380px; }
        .ref { font-weight: 600; color: var(--ink); background: var(--field); border: 1px solid var(--line); border-radius: 8px; padding: 4px 12px; font-variant-numeric: tabular-nums; }
        .another { background: transparent; color: var(--accent); border: 1px solid var(--line); font-size: 14px; padding: 10px 16px; text-decoration: none; border-radius: 10px; font-weight: 500; }
        .another:hover { background: var(--field); }
        footer { text-align: center; font-size: 13px; color: var(--muted); margin-top: 18px; }
        footer b { color: var(--accent); font-weight: 600; }

        @media (max-width: 900px) {
            .wrap { grid-template-columns: 1fr; gap: 28px; align-items: start; padding-top: 28px; }
            .intro { max-width: none; }
            .brand-mark { display: none; }
            .intro h1 { font-size: 28px; }
        }
        @media (max-width: 480px) { .card { padding: 26px 18px 22px; } }
    </style>
</head>
<body>
<main class="wrap">
    <section class="intro">
        <img class="logo" src="{{ url('assets/images/logo/tnbttech.png') }}" alt="TNBT">
        <h1>We're here to help</h1>
        <p>Having trouble with Market Audit? Tell us what happened, for example a login problem, an audit that won't submit or a report that won't download, and our team will reply by email.</p>
        <ul class="points">
            <li><span class="icon"><i class="fa fa-envelope"></i></span><div><b>We reply by email</b><span>Use an address you check: our answer goes there.</span></div></li>
            <li><span class="icon"><i class="fa fa-hashtag"></i></span><div><b>You get a reference number</b><span>We email you a copy of your message with its reference.</span></div></li>
            <li><span class="icon"><i class="fa fa-mobile"></i></span><div><b>Include details</b><span>Your project, outlet or screen name helps us find the problem faster.</span></div></li>
        </ul>
        <a class="back" href="{{ route('login') }}"><i class="fa fa-arrow-left"></i>&nbsp; Back to login</a>
        <img class="brand-mark" src="{{ url('assets/images/logo/tnbttech.png') }}" alt="">
    </section>

    <section>
        <div class="card">
            <div class="card-head">
                <img src="{{ url('assets/images/logo/tnbttech.png') }}" alt="TNBT">
                <h2>{{ session('support_sent') ? 'Message sent' : 'Send us a message' }}</h2>
            </div>

            @if (session('support_sent'))
                @php $sent = session('support_sent'); @endphp
                <div class="sent" role="status">
                    <div class="tick"><i class="fa fa-check"></i></div>
                    <h3>Thank you{{ $sent['name'] ? ', ' . $sent['name'] : '' }}</h3>
                    <p>We've received your message and will get back to you by email.</p>
                    @if ($sent['id'])
                        <span class="ref">Reference #{{ $sent['id'] }}</span>
                        <p>A confirmation has been sent to <b>{{ $sent['email'] }}</b>.</p>
                    @endif
                    <a class="another" href="{{ route('support.show') }}">Send another message</a>
                </div>
            @else
                <form method="POST" action="{{ route('support.store') }}" id="supportForm" novalidate>
                    @csrf
                    {{-- Hidden from people; bots that fill it in are ignored --}}
                    <div class="hp" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                    <div class="@error('name') invalid @enderror">
                        <label for="name">Name</label>
                        <div class="field"><span class="addon"><i class="fa fa-user"></i></span>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your name" maxlength="100" required autocomplete="name"></div>
                        @error('name')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div class="@error('email') invalid @enderror">
                        <label for="email">Email</label>
                        <div class="field"><span class="addon"><i class="fa fa-envelope"></i></span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" maxlength="150" required autocomplete="email"></div>
                        @error('email')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div class="@error('phone') invalid @enderror">
                        <label for="phone">Phone <small>(optional)</small></label>
                        <div class="field"><span class="addon"><i class="fa fa-phone"></i></span>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="Your phone number" maxlength="20" autocomplete="tel"></div>
                        @error('phone')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="How can we help?" maxlength="3000" required class="@error('message') invalid @enderror">{{ old('message') }}</textarea>
                        <div class="counter"><span id="messageCount">{{ strlen(old('message', '')) }}</span> / 3000</div>
                        @error('message')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" id="supportSubmit"><i class="fa fa-paper-plane"></i> Submit</button>
                </form>
            @endif
        </div>
        <footer>Copyright © <b>TNBTTech</b> {{ date('Y') - 1 }} - {{ date('Y') }}</footer>
    </section>
</main>

<script>
    (function () {
        var form = document.getElementById('supportForm');
        if (!form) return;
        var message = document.getElementById('message');
        var count = document.getElementById('messageCount');
        message.addEventListener('input', function () { count.textContent = message.value.length; });
        // One submission per click
        form.addEventListener('submit', function () {
            var button = document.getElementById('supportSubmit');
            button.disabled = true;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending…';
        });
    })();
</script>
</body>
</html>
