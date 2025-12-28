<?php

namespace App\Services;

use App\Models\BeneficialOwner;
use App\Models\BusinessVerification;
use App\Models\SanctionsScreeningResult;
use App\Services\AuditService;

class SanctionsScreeningService
{
    /**
     * Mock sanctions lists - In production, integrate with real APIs
     * Examples: OFAC (US), UN Security Council, EU Sanctions, etc.
     */
    protected array $mockSanctionedNames = [
        'Vladimir Putin',
        'Kim Jong Un',
        'Nicolas Maduro',
        'Bashar al-Assad',
        'Ali Khamenei',
    ];

    /**
     * Screen a beneficial owner against sanctions lists
     */
    public function screenBeneficialOwner(BeneficialOwner $ubo): SanctionsScreeningResult
    {
        // In production, this would call real APIs:
        // - OFAC API (US Treasury)
        // - UN Security Council Consolidated List
        // - EU Sanctions List
        // - UK HM Treasury
        // - World Bank Debarment List
        
        $screeningLists = [
            'OFAC' => 'Office of Foreign Assets Control (US)',
            'UN' => 'UN Security Council Consolidated List',
            'EU' => 'EU Financial Sanctions',
            'INTERPOL' => 'INTERPOL Red Notices',
        ];

        // Check for exact or fuzzy matches
        $matches = $this->checkForMatches($ubo->full_name);
        
        // Calculate match score (0-100)
        $matchScore = empty($matches) ? 0 : $this->calculateMatchScore($ubo->full_name, $matches);
        
        // Determine status
        $status = $this->determineStatus($matchScore);

        // Create screening result
        $result = SanctionsScreeningResult::create([
            'business_verification_id' => $ubo->business_verification_id,
            'beneficial_owner_id' => $ubo->id,
            'screened_name' => $ubo->full_name,
            'screening_type' => 'individual',
            'status' => $status,
            'screening_lists' => $screeningLists,
            'matches' => $matches,
            'match_score' => $matchScore,
            'screened_at' => now(),
        ]);

        // If clear, auto-approve UBO sanctions
        if ($status === 'clear') {
            $ubo->update(['sanctions_cleared' => true]);
        }

        // Audit log
        AuditService::log(
            'sanctions.screening.completed',
            "Sanctions screening completed for {$ubo->full_name}: {$status}",
            $result,
            [],
            ['name' => $ubo->full_name, 'status' => $status, 'score' => $matchScore],
            ['ubo_id' => $ubo->id]
        );

        return $result;
    }

    /**
     * Screen all UBOs for a business verification
     */
    public function screenAllUbos(BusinessVerification $verification): array
    {
        $results = [];
        
        foreach ($verification->beneficialOwners as $ubo) {
            $results[] = $this->screenBeneficialOwner($ubo);
        }

        // Check if all UBOs are cleared
        $allCleared = $verification->beneficialOwners()
            ->where('sanctions_cleared', false)
            ->count() === 0;

        if ($allCleared) {
            $verification->update(['sanctions_screening_completed' => true]);
        }

        return $results;
    }

    /**
     * Screen business entity against sanctions lists
     */
    public function screenBusinessEntity(BusinessVerification $verification): SanctionsScreeningResult
    {
        $screeningLists = [
            'OFAC_SDN' => 'OFAC Specially Designated Nationals',
            'EU_ENTITIES' => 'EU Sanctioned Entities',
            'UN_ENTITIES' => 'UN Sanctioned Entities',
        ];

        $matches = $this->checkForMatches($verification->business_name);
        $matchScore = empty($matches) ? 0 : $this->calculateMatchScore($verification->business_name, $matches);
        $status = $this->determineStatus($matchScore);

        return SanctionsScreeningResult::create([
            'business_verification_id' => $verification->id,
            'screened_name' => $verification->business_name,
            'screening_type' => 'entity',
            'status' => $status,
            'screening_lists' => $screeningLists,
            'matches' => $matches,
            'match_score' => $matchScore,
            'screened_at' => now(),
        ]);
    }

    /**
     * Check for matches in sanctions lists (MOCK)
     */
    protected function checkForMatches(string $name): array
    {
        $matches = [];
        
        foreach ($this->mockSanctionedNames as $sanctionedName) {
            // Simple case-insensitive contains check
            // In production: use fuzzy matching algorithms (Levenshtein, Soundex, etc.)
            if (stripos($name, $sanctionedName) !== false || stripos($sanctionedName, $name) !== false) {
                $matches[] = [
                    'name' => $sanctionedName,
                    'list' => 'OFAC',
                    'match_type' => 'partial',
                    'confidence' => 85,
                ];
            }
        }

        return $matches;
    }

    /**
     * Calculate match score
     */
    protected function calculateMatchScore(string $searchName, array $matches): int
    {
        if (empty($matches)) {
            return 0;
        }

        // Return highest confidence match
        $highestConfidence = 0;
        foreach ($matches as $match) {
            if ($match['confidence'] > $highestConfidence) {
                $highestConfidence = $match['confidence'];
            }
        }

        return $highestConfidence;
    }

    /**
     * Determine status based on match score
     */
    protected function determineStatus(int $matchScore): string
    {
        if ($matchScore === 0) {
            return 'clear';
        } elseif ($matchScore < 70) {
            return 'potential_match';
        } else {
            return 'confirmed_match';
        }
    }

    /**
     * Admin: Clear a potential match
     */
    public function clearMatch(SanctionsScreeningResult $result, $admin, string $notes): void
    {
        $result->update([
            'status' => 'clear',
            'notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Mark UBO as cleared
        if ($result->beneficial_owner_id) {
            $result->beneficialOwner->update(['sanctions_cleared' => true]);
        }

        AuditService::log(
            'sanctions.match.cleared',
            "Sanctions match cleared by admin",
            $result,
            ['status' => 'potential_match'],
            ['status' => 'clear'],
            ['admin_id' => $admin->id]
        );
    }

    /**
     * Admin: Confirm a match
     */
    public function confirmMatch(SanctionsScreeningResult $result, $admin, string $notes): void
    {
        $result->update([
            'status' => 'confirmed_match',
            'notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log(
            'sanctions.match.confirmed',
            "Sanctions match confirmed by admin",
            $result,
            [],
            ['status' => 'confirmed_match'],
            ['admin_id' => $admin->id]
        );
    }
}
