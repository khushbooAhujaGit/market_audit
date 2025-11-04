<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $file_path;
    public $zipFilePath;

    /**
     * Create a new message instance.
     */
    public function __construct($file_path, $zipFilePath = null)
    {
        $this->file_path = $file_path;
        //khushboo 23-05-25
        $this->zipFilePath = $zipFilePath;
//        dd($zipFilePath);
        //khushboo 23-05-25
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Report Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.sendReportMail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */

    public function attachments(): array
    {
//        if (isset($this->file_path)) {
//            return [
//                Attachment::fromPath(public_path($this->file_path)),
//            ];
//        }
//        return [];

        //khushboo 23-05-25
        $attachments = [];

        // Attach the primary file if provided
        if (isset($this->file_path)) {
            $attachments[] = Attachment::fromPath(public_path($this->file_path));
        }

        // Attach all PDF files if provided
//        foreach ($this->pdfFilePaths as $pdfPath) {
//
//            // Remove the base URL to get the relative path
//            $relativePath = str_replace(url('/') . '/', '', $pdfPath);
//            $fullPath = public_path($relativePath);
//
////            dd($fullPath, file_exists($fullPath));
//            if (file_exists($fullPath)) {
//                $attachments[] = Attachment::fromPath($fullPath);
//            }
//        }

        // Attach the ZIP file if it exists
//        dd(isset($this->zipFilePath));
        if (isset($this->zipFilePath)) {
            // Remove the base URL to get the relative path

//            $relativePath = str_replace(url('/') . '/', '', $this->zipFilePath);
//            $fullPath = public_path($relativePath);

//            dd($this->zipFilePath);
            if (file_exists($this->zipFilePath)) {
                $attachments[] = Attachment::fromPath($this->zipFilePath);
            }
        }

//        dd($attachments);
        return $attachments;
        //khushboo 23-05-25
    }
}
