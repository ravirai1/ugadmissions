<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\State;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;

/** Fills the application form steps through HTTP, as an applicant would. Needs $this->user. */
trait FillsApplication
{
    private function c12(string $name): int
    {
        return Class12Subject::where('name', $name)->value('id');
    }

    private function sub(string $code): int
    {
        return Subject::where('code', $code)->value('id');
    }

    private function basic(array $o = []): array
    {
        return array_merge([
            'dob' => '2008-05-10', 'gender' => 'Female', 'blood_group' => 'B+', 'nationality' => 'Indian', 'mother_tongue' => 'Malayalam',
            'father_title' => 'Dr.', 'father_name' => 'John Thomas', 'father_qualification' => 'Postgraduate', 'father_occupation' => 'Teaching / academic',
            'father_designation' => 'Associate Professor', 'father_organisation' => 'University of Delhi', 'father_office_address' => 'Faculty of Arts, Delhi 110007',
            'father_mobile' => '9811122233', 'father_email' => 'john@example.com',
            'mother_title' => 'Mrs.', 'mother_name' => 'Mary Thomas', 'mother_qualification' => 'Graduate', 'mother_occupation' => 'Homemaker',
            'family_income' => '5_8', 'last_school' => 'Modern School', 'last_school_city' => 'Delhi',
        ], $o);
    }

    private function address(): array
    {
        $delhi = State::where('name', 'Delhi')->value('id');

        return [
            'cur_line1' => '12 Main Road', 'cur_city' => 'New Delhi', 'cur_state_id' => $delhi, 'cur_district_other' => 'Central Delhi',
            'cur_pin' => '110001', 'perm_same' => '1', 'domicile_state_id' => State::where('name', 'Kerala')->value('id'),
        ];
    }

    private function christian(array $o = []): array
    {
        return array_merge([
            'category_code' => Category::CHRISTIAN, 'christian_subcategory' => 'CNI', 'baptism_date' => '2008-08-01',
            'baptism_church' => 'Cathedral Church of the Redemption', 'baptism_place' => 'New Delhi', 'baptism_priest' => 'A. Kumar',
            'denomination' => 'CNI', 'current_church' => 'St. James Church', 'current_priest' => 'P. Singh', 'current_church_address' => 'Church Road, Delhi 110054', 'current_priest_mobile' => '9810011122', 'current_priest_email' => 'stjames@example.org',
        ], $o);
    }

    private function academics(array $subjects = ['English Core', 'Mathematics', 'Physics', 'Chemistry', 'Economics']): array
    {
        return [
            'c10_board' => 'CBSE', 'c10_year' => 2024, 'c10_roll_no' => '123', 'c10_max' => 500, 'c10_obtained' => 450,
            'c12_board' => 'CBSE', 'c12_year' => 2026, 'c12_roll_no' => '456',
            'marks' => collect($subjects)->map(fn ($s) => ['subject' => $this->c12($s), 'max' => 100, 'obtained' => 90])->all(),
        ];
    }

    private function cuet(array $papers = ['ENG' => 180, 'MAT' => 190, 'PHY' => 170, 'CHE' => 160, 'ECO' => 150]): array
    {
        return [
            'cuet_roll_no' => 'CU27001',
            'scores' => collect($papers)->map(fn ($score, $code) => ['subject' => $this->sub($code), 'score' => $score])->values()->all(),
        ];
    }

    private function go(string $step, array $data)
    {
        return $this->actingAs($this->user)->post("/application/$step", $data);
    }

    private function fillToCourses(): void
    {
        $this->go('basic', $this->basic())->assertSessionHasNoErrors();
        $this->go('address', $this->address())->assertSessionHasNoErrors();
        $this->go('category', $this->christian())->assertSessionHasNoErrors();
        $this->go('academics', $this->academics())->assertSessionHasNoErrors();
        $this->go('cuet', $this->cuet())->assertSessionHasNoErrors();
    }

    /** A photo (white background, a "face" in the middle) or signature (dark ink on white) that passes the checks. */
    private function goodImage(string $type, ?array $bg = null): UploadedFile
    {
        [$w, $h] = $type === 'photo' ? [350, 450] : [420, 140];
        $img = imagecreatetruecolor($w, $h);
        $bgc = $bg ? imagecolorallocate($img, ...$bg) : imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bgc);
        mt_srand(7);
        if ($type === 'photo') {
            for ($y = 120; $y < $h; $y++) {           // noisy "face and shoulders" so the file has a realistic size
                for ($x = 90; $x < $w - 90; $x++) {
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, mt_rand(80, 200), mt_rand(60, 160), mt_rand(40, 140)));
                }
            }
        } else {
            $ink = imagecolorallocate($img, 20, 20, 90);
            imagesetthickness($img, 4);
            for ($i = 0; $i < 6; $i++) {
                imageline($img, 30 + $i * 60, 100, 70 + $i * 60, 30, $ink);
                imagearc($img, 60 + $i * 60, 70, 50, 40, 0, 300, $ink);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        imagejpeg($img, $path, 95);
        imagedestroy($img);

        return new UploadedFile($path, "$type.jpg", 'image/jpeg', null, true);
    }
}
