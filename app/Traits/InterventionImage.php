<?php

namespace App\Traits;

use App\Models\AuditorMarkInMarkOut;
use App\Models\TempUserActivityAnswersData;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait  InterventionImage
{

    public function convertToGeoLocationImage($imagePath, $latitude, $longitude, $answerdata, $projectMonthYearDirectory, $address_details)
    {

        $geo_image = null;

        $info = pathinfo($imagePath);
        Log::info('Image pathinfo:', $info);

        $filename = uniqid() . $info['filename']; // name without extension
        Log::info('image name - '. $filename);
        $extension = isset($info['extension']) ? $info['extension'] : 'jpg';

        $image_height = 1920;
        $image_width = 1440;
        $img = ImageManager::gd()->read($imagePath);
        $img->resize($image_width, $image_height);


//        $x = 1;  // X position
//        $y = 1920; // Y position
//        $padding = 10;
//        $rectangleWidth = 1440;  // Background width
//        $rectangleHeight = 300;  // Background height

        $image_height = 1920;
        $image_width = 1440;
        $img = ImageManager::gd()->read($imagePath);
        $img->resize($image_width, $image_height);

        $textBlockHeight = 150; // Approx height for all the lines
        $bottomMargin = 10; // Space from the bottom of the image
        $y = $image_height - $textBlockHeight - $bottomMargin; // Position slightly above the bottom

        $x = 1;
        $padding = 10;
        $rectangleWidth = 1440;
        $rectangleHeight = $textBlockHeight + 40;

        $img->drawRectangle(0, $y - 20, function (RectangleFactory $rectangle) use ($rectangleWidth, $rectangleHeight) {
            $rectangle->size($rectangleWidth, $rectangleHeight);
            $rectangle->background('rgba(64, 63, 62, 0.8)');
        });

        $containerWidth = 1200; // for example, width of your image or text block
        $horizontalPadding = 20; // left & right padding

        $address_details = $this->returnAddressDetailsUsingLatLong($latitude, $longitude);

        $main_address = $address_details["district"] . ", " . $address_details["state"] . ', India';
        $img->text($main_address, $horizontalPadding, $y, function (FontFactory $font) use ($containerWidth, $horizontalPadding) {
            $font->size(28);
            $font->filename(public_path("assets/fonts/arial/ARIAL.TTF"));
            $font->color('white'); // White text
            $font->align('start');
            $font->valign('middle');
            $font->lineHeight(1.5);
        });

        $location_text = $address_details["location"];
        $wrappedText = wordwrap($location_text, 130, "\n", false);
        $lines = explode("\n", $wrappedText);; // Starting Y position

        $y = $y + 30;
        foreach ($lines as $line) {
            $img->text($line, $horizontalPadding, $y, function (FontFactory $font) use ($containerWidth, $horizontalPadding) {
                $font->size(23);
                $font->filename(public_path("assets/fonts/arial/ARIAL.TTF"));
                $font->color('white'); // White text
                $font->align('start');
                $font->valign('middle');
                $font->lineHeight(1.5);
            });
            $y += 30; // Move Y position down for the next line
        }

        $labelX = $horizontalPadding;
        $valueX = $horizontalPadding + 100; // Adjust spacing as needed


        $img->text("Latitude : " . $latitude, $horizontalPadding, $y, function (FontFactory $font) use ($containerWidth, $horizontalPadding) {
            $font->size(23);
            $font->filename(public_path("assets/fonts/arial/ARIAL.TTF"));
            $font->color('white'); // White text
            $font->align('start');
            $font->valign('middle');
            $font->lineHeight(1.5);
        });

        $img->text("Longitude : " . $longitude, $horizontalPadding, $y = $y + 30, function (FontFactory $font) use ($containerWidth, $horizontalPadding) {
            $font->size(23);
            $font->filename(public_path("assets/fonts/arial/ARIAL.TTF"));
            $font->color('white');
            $font->align('start');
            $font->valign('middle');
            $font->lineHeight(1.5);
        });

        $y += 30;

        $time = $answerdata->created_at
            ? Carbon::parse($answerdata->created_at)->format('d-m-Y H:i:s')
            : 'N/A';

        $img->text("Time : " . $time, $horizontalPadding, $y, function (FontFactory $font) {
            $font->size(23);
            $font->filename(public_path("assets/fonts/arial/ARIAL.TTF"));
            $font->color('white');
            $font->align('start');
            $font->valign('middle');
            $font->lineHeight(1.5);
        });

        // Save the image
        $file_unique_name = $filename . '.' . $extension;
        $full_file_path = $projectMonthYearDirectory . '/' . $file_unique_name;
        $outputPath = public_path($projectMonthYearDirectory . '/' . $file_unique_name);
        $img->save($outputPath);

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        $user_answer = TempUserActivityAnswersData::find($answerdata->id);
        $user_answer->user_answer = $full_file_path;
        $user_answer->save();

        Log::info('image conversion done');

    }


    function returnAddressDetailsUsingLatLong($latitude, $longitude)
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; MyLaravelApp/1.0; +http://yourwebsite.com)'
        ])->get("https://nominatim.openstreetmap.org/reverse", [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
            'addressdetails' => 1 // Enables detailed address breakdown
        ]);

        $data = $response->json(); // Dump response

        $address_details = [
            "latitude" => $data["lat"] ?? '',
            "longitude" => $data["lon"] ?? '',
            "location" => $data["display_name"] ?? '',
            "district" => $data["address"]["state_district"] ?? '',
            "state" => $data["address"]["state"] ?? '',
            "postcode" => $data["address"]["postcode"] ?? '',
            "ISO3166-2-lvl4" => $data["address"]["ISO3166-2-lvl4"] ?? '',
        ];
        return $address_details;
    }

}
