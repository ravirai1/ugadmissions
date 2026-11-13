<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\Course;
use App\Models\Department;
use App\Models\PwbdType;
use App\Models\State;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Starting master data. Everything here is editable later from the admin panel (step 4).
 * Course eligibility rules are a starting point: verify against the DU CSAS (UG) 2027 bulletin.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->states();
        $this->categories();
        $this->pwbd();
        $this->subjects();
        $this->class12();
        $this->courses();
    }

    private function states(): void
    {
        $states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
            'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
            'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
            'Uttar Pradesh', 'Uttarakhand', 'West Bengal'];
        $uts = ['Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi',
            'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'];

        foreach ($states as $s) {
            State::updateOrCreate(['name' => $s], ['is_ut' => false]);
        }
        foreach ($uts as $s) {
            State::updateOrCreate(['name' => $s], ['is_ut' => true]);
        }
        State::updateOrCreate(['name' => 'Outside India'], ['is_ut' => false, 'sort' => 999]);
    }

    private function categories(): void
    {
        $rows = [
            ['UR', 'General / Unreserved', null, null, 1],
            ['OBC', 'OBC (Non-Creamy Layer)', null, 'OBC-NCL certificate', 2],
            ['SC', 'Scheduled Caste (SC)', null, 'SC certificate', 3],
            ['ST', 'Scheduled Tribe (ST)', null, 'ST certificate', 4],
            ['EWS', 'Economically Weaker Section (EWS)', null, 'EWS certificate', 5],
            [Category::CHRISTIAN, 'Christian', null, 'Baptism certificate and priest\'s letter', 6],
            ['CNI', 'CNI – Church of North India', Category::CHRISTIAN, null, 1],
            ['CNID', 'CNID – Church of North India, Delhi Diocese', Category::CHRISTIAN, null, 2],
            ['COTH', 'COTH – Other Christian denominations', Category::CHRISTIAN, null, 3],
        ];
        foreach ($rows as [$code, $name, $parent, $cert, $sort]) {
            Category::updateOrCreate(['code' => $code], ['name' => $name, 'parent_code' => $parent, 'certificate_label' => $cert, 'sort' => $sort]);
        }
    }

    private function pwbd(): void
    {
        $groups = [
            1 => ['Blindness and Low Vision', ['Blindness', 'Low Vision']],
            2 => ['Deaf and Hard of Hearing', ['Deaf', 'Hard of Hearing']],
            3 => ['Locomotor Disability', ['Locomotor Disability', 'Cerebral Palsy', 'Leprosy Cured', 'Dwarfism', 'Acid Attack Victim', 'Muscular Dystrophy']],
            4 => ['Autism, Intellectual Disability, Specific Learning Disability and Mental Illness', ['Autism Spectrum Disorder', 'Intellectual Disability', 'Specific Learning Disability', 'Mental Illness']],
            5 => ['Multiple Disabilities', ['Multiple Disabilities (from groups 1–4)', 'Deaf-Blindness']],
        ];
        foreach ($groups as $no => [$group, $types]) {
            foreach ($types as $t) {
                PwbdType::updateOrCreate(['group_no' => $no, 'name' => $t], ['group_name' => $group]);
            }
        }
    }

    private function subjects(): void
    {
        $languages = ['ASM' => 'Assamese', 'BEN' => 'Bengali', 'ENG' => 'English', 'GUJ' => 'Gujarati', 'HIN' => 'Hindi',
            'KAN' => 'Kannada', 'MAL' => 'Malayalam', 'MAR' => 'Marathi', 'ODI' => 'Odia', 'PUN' => 'Punjabi',
            'TAM' => 'Tamil', 'TEL' => 'Telugu', 'URD' => 'Urdu'];
        $domains = [
            'ACC' => 'Accountancy / Book Keeping', 'AGR' => 'Agriculture', 'BIO' => 'Biology / Biological Studies / Biotechnology / Biochemistry',
            'BST' => 'Business Studies', 'CHE' => 'Chemistry', 'CSC' => 'Computer Science / Informatics Practices',
            'ECO' => 'Economics / Business Economics', 'EVS' => 'Environmental Science', 'FIA' => 'Fine Arts / Visual Arts / Commercial Arts',
            'GEO' => 'Geography / Geology', 'HIS' => 'History', 'HSC' => 'Home Science',
            'KTP' => 'Knowledge Tradition and Practices of India', 'MMC' => 'Mass Media / Mass Communication',
            'MAT' => 'Mathematics / Applied Mathematics', 'PFA' => 'Performing Arts', 'PED' => 'Physical Education',
            'PHY' => 'Physics', 'POL' => 'Political Science', 'PSY' => 'Psychology', 'SAN' => 'Sanskrit', 'SOC' => 'Sociology',
        ];
        $i = 0;
        foreach ($languages as $code => $name) {
            Subject::updateOrCreate(['code' => $code], ['name' => $name, 'type' => Subject::LANGUAGE, 'sort' => ++$i]);
        }
        foreach ($domains as $code => $name) {
            Subject::updateOrCreate(['code' => $code], ['name' => $name, 'type' => Subject::DOMAIN, 'sort' => ++$i]);
        }
        Subject::updateOrCreate(['code' => 'GAT'], ['name' => 'General Aptitude Test', 'type' => Subject::GENERAL, 'sort' => ++$i]);
    }

    private function class12(): void
    {
        $map = [
            'English' => 'ENG', 'English Core' => 'ENG', 'English Elective' => 'ENG', 'Hindi' => 'HIN', 'Hindi Core' => 'HIN',
            'Hindi Elective' => 'HIN', 'Urdu' => 'URD', 'Bengali' => 'BEN', 'Assamese' => 'ASM', 'Gujarati' => 'GUJ',
            'Kannada' => 'KAN', 'Malayalam' => 'MAL', 'Marathi' => 'MAR', 'Odia' => 'ODI', 'Punjabi' => 'PUN',
            'Tamil' => 'TAM', 'Telugu' => 'TEL',
            'Accountancy' => 'ACC', 'Agriculture' => 'AGR', 'Biology' => 'BIO', 'Biotechnology' => 'BIO', 'Biochemistry' => 'BIO',
            'Business Studies' => 'BST', 'Commerce' => 'BST', 'Chemistry' => 'CHE', 'Computer Science' => 'CSC',
            'Informatics Practices' => 'CSC', 'Economics' => 'ECO', 'Business Economics' => 'ECO',
            'Environmental Science' => 'EVS', 'Fine Arts' => 'FIA', 'Painting' => 'FIA', 'Geography' => 'GEO', 'Geology' => 'GEO',
            'History' => 'HIS', 'Home Science' => 'HSC', 'Knowledge Tradition and Practices of India' => 'KTP',
            'Mass Media Studies' => 'MMC', 'Mathematics' => 'MAT', 'Applied Mathematics' => 'MAT', 'Music' => 'PFA', 'Dance' => 'PFA',
            'Physical Education' => 'PED', 'Physics' => 'PHY', 'Political Science' => 'POL', 'Psychology' => 'PSY',
            'Sanskrit' => 'SAN', 'Sanskrit Core' => 'SAN', 'Sanskrit Elective' => 'SAN', 'Sociology' => 'SOC',
            'Legal Studies' => null, 'Entrepreneurship' => null, 'Philosophy' => null, 'Other (no CUET paper)' => null,
        ];
        $codes = Subject::pluck('id', 'code');
        foreach ($map as $name => $code) {
            Class12Subject::updateOrCreate(['name' => $name], ['subject_id' => $code ? $codes[$code] : null]);
        }
    }

    private function courses(): void
    {
        $dept = fn (string $name, string $code) => Department::firstOrCreate(['code' => $code], ['name' => $name])->id;
        $anyLang = ['count' => 1, 'allowed' => null];

        $courses = [
            ['BA-ENG', 'B.A. (Hons.) English', $dept('English', 'ENG'), true, [
                'class12_required' => ['ENG'], 'languages' => ['count' => 1, 'allowed' => ['ENG']], 'domains' => ['count' => 2, 'allowed' => null],
            ]],
            ['BA-ECO', 'B.A. (Hons.) Economics', $dept('Economics', 'ECO'), true, [
                'class12_required' => ['MAT'], 'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null, 'must_include' => ['MAT']],
            ]],
            ['BA-HIS', 'B.A. (Hons.) History', $dept('History', 'HIS'), true, [
                'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null], 'general_test' => true,
            ]],
            ['BA-PHI', 'B.A. (Hons.) Philosophy', $dept('Philosophy', 'PHI'), true, [
                'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null], 'general_test' => true,
            ]],
            ['BA-SAN', 'B.A. (Hons.) Sanskrit', $dept('Sanskrit', 'SAN'), false, [
                'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null, 'must_include' => ['SAN']],
            ]],
            ['BA-PROG', 'B.A. Programme', $dept('B.A. Programme', 'BAP'), true, [
                'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null], 'general_test' => true,
                'programme_options' => ['Economics + History', 'Economics + Mathematics', 'Economics + Philosophy', 'History + Philosophy', 'History + Sanskrit', 'Philosophy + Sanskrit'],
            ]],
            ['BSC-MAT', 'B.Sc. (Hons.) Mathematics', $dept('Mathematics', 'MAT'), true, [
                'class12_required' => ['MAT'], 'languages' => $anyLang, 'domains' => ['count' => 2, 'allowed' => null, 'must_include' => ['MAT']],
            ]],
            ['BSC-PHY', 'B.Sc. (Hons.) Physics', $dept('Physics', 'PHY'), false, [
                'class12_required' => ['PHY', 'MAT'], 'languages' => $anyLang,
                'combinations' => [['label' => 'PCM (Physics, Chemistry, Mathematics)', 'papers' => ['PHY', 'CHE', 'MAT']]],
            ]],
            ['BSC-CHE', 'B.Sc. (Hons.) Chemistry', $dept('Chemistry', 'CHE'), true, [
                'class12_required' => ['CHE'], 'languages' => $anyLang,
                'combinations' => [
                    ['label' => 'PCM (Physics, Chemistry, Mathematics)', 'papers' => ['PHY', 'CHE', 'MAT']],
                    ['label' => 'PCB (Physics, Chemistry, Biology)', 'papers' => ['PHY', 'CHE', 'BIO']],
                ],
            ]],
            ['BSC-PS', 'B.Sc. Programme (Physical Sciences)', $dept('Physical Sciences', 'PS'), false, [
                'languages' => $anyLang,
                'combinations' => [
                    ['label' => 'PCM (Physics, Chemistry, Mathematics)', 'papers' => ['PHY', 'CHE', 'MAT']],
                    ['label' => 'PMCS (Physics, Mathematics, Computer Science)', 'papers' => ['PHY', 'MAT', 'CSC']],
                ],
            ]],
        ];

        foreach ($courses as $i => [$code, $name, $deptId, $jmc, $rules]) {
            Course::updateOrCreate(['code' => $code], [
                'name' => $name, 'department_id' => $deptId, 'offered_at_jmc' => $jmc, 'eligibility' => $rules, 'sort' => $i + 1,
            ]);
        }
    }
}
