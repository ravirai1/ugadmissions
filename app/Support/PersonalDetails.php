<?php

namespace App\Support;

/**
 * Dropdown lists for the Basic step: blood group, nationality, mother tongue and parents' details.
 */
class PersonalDetails
{
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Not known'];

    public const NATIONALITIES = ['Indian', 'OCI / PIO card holder', 'Nepalese', 'Bhutanese', 'Tibetan', 'Other foreign national'];

    /** The 22 scheduled languages, English, and Other. */
    public const MOTHER_TONGUES = [
        'Assamese', 'Bengali', 'Bodo', 'Dogri', 'English', 'Gujarati', 'Hindi', 'Kannada', 'Kashmiri', 'Konkani', 'Maithili', 'Malayalam',
        'Manipuri', 'Marathi', 'Nepali', 'Odia', 'Punjabi', 'Sanskrit', 'Santali', 'Sindhi', 'Tamil', 'Telugu', 'Urdu', 'Other',
    ];

    /** Title before a parent's name. "Late" means the parent has passed away, so work details are not asked. */
    public const TITLES = [
        'Mr.', 'Mrs.', 'Ms.', 'Shri', 'Smt.', 'Dr.', 'Prof.', 'Rev.', 'Rt. Rev.', 'Fr.', 'Sr.', 'Pastor',
        'Adv.', 'Er.', 'CA', 'Capt.', 'Maj.', 'Lt. Col.', 'Col.', 'Brig.', 'Justice', 'Late',
    ];

    public const DECEASED = 'Late';

    public const QUALIFICATIONS = [
        'Below Class 10', 'Class 10', 'Class 12', 'Diploma / ITI', 'Graduate', 'Postgraduate',
        'Professional degree (MBBS, LLB, B.Tech, CA, CS etc.)', 'M.Phil. / Ph.D.', 'No formal education',
    ];

    /** Occupations that mean the parent is working, so the office details are asked. */
    public const WORKING = [
        'Central Government service', 'State Government service', 'Public sector (PSU / bank)', 'Private sector',
        'Business / self-employed', 'Doctor / lawyer / other professional', 'Teaching / academic', 'Clergy / church ministry',
        'Armed forces / police', 'Farming / agriculture', 'NGO / social work', 'Other',
    ];

    public const NOT_WORKING = ['Homemaker', 'Retired', 'Not working'];

    public static function occupations(): array
    {
        return array_merge(self::WORKING, self::NOT_WORKING);
    }

    /** "Prof. John Thomas", or just the name when no title is saved. */
    public static function withTitle(?string $title, ?string $name): string
    {
        return trim(($title ? $title.' ' : '').$name);
    }
}
