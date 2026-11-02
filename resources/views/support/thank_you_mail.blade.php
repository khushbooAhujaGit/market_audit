{{-- Thank-you mail to the person who sent a support message (HTML, inline styles for mail apps) --}}
<div style="font-family: Arial, Helvetica, sans-serif; color: #1c2a33; font-size: 15px; line-height: 1.6; max-width: 560px;">
    <p style="margin: 0 0 14px;">Hi {{ $supportRequest->name }},</p>
    <p style="margin: 0 0 14px;">Thank you for contacting Market Audit support. We've received your message and will get back to you at this email address.</p>
    <p style="margin: 0 0 6px;"><b>Reference:</b> #{{ $supportRequest->id }}</p>
    <p style="margin: 0 0 6px;"><b>Received:</b> {{ $supportRequest->created_at->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
    <p style="margin: 14px 0 6px;"><b>Your message:</b></p>
    <div style="background: #f4f7f9; border: 1px solid #dbe3e8; border-radius: 8px; padding: 12px 14px; white-space: pre-wrap;">{{ $supportRequest->message }}</div>
    <p style="margin: 18px 0 0;">Please quote the reference number if you write to us about this again.</p>
    <p style="margin: 18px 0 0;">Regards,<br>Market Audit Support<br>TNBTTech</p>
</div>
