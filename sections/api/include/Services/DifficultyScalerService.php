<?php

namespace Services;

class DifficultyScalerService
{
    private $difficultyMultipliers = [
        'easy' => [
            'efficiency' => 0.6,
            'reaction_time' => 1.5,
            'resource_optimization' => 0.5,
            'military_skill' => 0.5,
            'decision_quality' => 0.6,
            'error_rate' => 0.30
        ],
        'medium' => [
            'efficiency' => 0.8,
            'reaction_time' => 1.0,
            'resource_optimization' => 0.75,
            'military_skill' => 0.75,
            'decision_quality' => 0.8,
            'error_rate' => 0.15
        ],
        'hard' => [
            'efficiency' => 1.0,
            'reaction_time' => 0.8,
            'resource_optimization' => 0.9,
            'military_skill' => 0.9,
            'decision_quality' => 0.95,
            'error_rate' => 0.05
        ],
        'expert' => [
            'efficiency' => 1.2,
            'reaction_time' => 0.5,
            'resource_optimization' => 1.0,
            'military_skill' => 1.0,
            'decision_quality' => 1.0,
            'error_rate' => 0.0
        ]
    ];

    public function __construct()
    {
    }

    public function scaleDifficulty(array $decision, string $difficulty): array
    {
        $difficulty = strtolower($difficulty);
        $multipliers = $this->getDifficultyMultipliers($difficulty);
        
        if (empty($multipliers)) {
            return $decision;
        }
        
        $decision = $this->applyEfficiencyScaling($decision, $multipliers);
        
        $decision = $this->introduceErrors($decision, $difficulty, $multipliers['error_rate']);
        
        $decision = $this->adjustReactionTime($decision, $multipliers['reaction_time']);
        
        $decision = $this->optimizeResources($decision, $multipliers['resource_optimization']);
        
        $decision = $this->scaleMilitarySkill($decision, $multipliers['military_skill']);
        
        return $decision;
    }

    public function getDifficultyMultipliers(string $difficulty): array
    {
        $difficulty = strtolower($difficulty);
        
        if (!isset($this->difficultyMultipliers[$difficulty])) {
            return $this->difficultyMultipliers['medium'];
        }
        
        return $this->difficultyMultipliers[$difficulty];
    }

    public function introduceErrors(array $decision, string $difficulty, ?float $errorRate = null): array
    {
        $difficulty = strtolower($difficulty);
        
        if ($errorRate === null) {
            $multipliers = $this->getDifficultyMultipliers($difficulty);
            $errorRate = $multipliers['error_rate'];
        }
        
        if ($errorRate <= 0) {
            return $decision;
        }
        
        $random = mt_rand(0, 100) / 100;
        
        if ($random < $errorRate) {
            $decision = $this->applySuboptimalDecision($decision, $difficulty);
            $decision['error_introduced'] = true;
            $decision['confidence'] = max(0.3, ($decision['confidence'] ?? 0.8) - 0.3);
        }
        
        return $decision;
    }

    public function getDecisionDelay(string $difficulty): int
    {
        $multipliers = $this->getDifficultyMultipliers($difficulty);
        $baseDelay = 5;
        
        return (int)($baseDelay * $multipliers['reaction_time']);
    }

    private function applyEfficiencyScaling(array $decision, array $multipliers): array
    {
        if (!isset($decision['parameters'])) {
            $decision['parameters'] = [];
        }
        
        if (isset($decision['parameters']['resource_allocation'])) {
            $decision['parameters']['resource_allocation'] *= $multipliers['efficiency'];
        }
        
        if (isset($decision['parameters']['troop_ratio'])) {
            $decision['parameters']['troop_ratio'] *= $multipliers['efficiency'];
        }
        
        $decision['efficiency_scaled'] = true;
        
        return $decision;
    }

    private function adjustReactionTime(array $decision, float $reactionMultiplier): array
    {
        if (!isset($decision['parameters'])) {
            $decision['parameters'] = [];
        }
        
        $decision['parameters']['execution_delay'] = (int)(300 * $reactionMultiplier);
        
        if ($reactionMultiplier < 1.0) {
            $decision['parameters']['priority'] = 'high';
        } elseif ($reactionMultiplier > 1.0) {
            $decision['parameters']['priority'] = 'low';
        }
        
        return $decision;
    }

    private function optimizeResources(array $decision, float $optimization): array
    {
        if (!isset($decision['parameters'])) {
            $decision['parameters'] = [];
        }
        
        if ($decision['action'] === 'build') {
            $decision['parameters']['resource_efficiency'] = $optimization;
            
            if ($optimization < 0.7) {
                $decision['parameters']['allow_waste'] = true;
            }
        }
        
        if ($decision['action'] === 'train') {
            $decision['parameters']['resource_efficiency'] = $optimization;
            
            if ($optimization < 0.7) {
                $decision['parameters']['batch_optimization'] = 'disabled';
            } else {
                $decision['parameters']['batch_optimization'] = 'enabled';
            }
        }
        
        return $decision;
    }

    private function scaleMilitarySkill(array $decision, float $skill): array
    {
        if (!isset($decision['parameters'])) {
            $decision['parameters'] = [];
        }
        
        if ($decision['action'] === 'attack' || $decision['action'] === 'farm') {
            $decision['parameters']['military_skill'] = $skill;
            
            if ($skill < 0.7) {
                $decision['parameters']['target_selection'] = 'random';
                $decision['parameters']['timing_optimization'] = false;
            } elseif ($skill >= 0.9) {
                $decision['parameters']['target_selection'] = 'optimal';
                $decision['parameters']['timing_optimization'] = true;
                $decision['parameters']['route_optimization'] = true;
            } else {
                $decision['parameters']['target_selection'] = 'good';
                $decision['parameters']['timing_optimization'] = true;
            }
        }
        
        if ($decision['action'] === 'defend') {
            $decision['parameters']['defense_skill'] = $skill;
            
            if ($skill >= 0.9) {
                $decision['parameters']['evasion_timing'] = 'optimal';
                $decision['parameters']['reinforcement_management'] = 'advanced';
            } elseif ($skill < 0.7) {
                $decision['parameters']['evasion_timing'] = 'basic';
                $decision['parameters']['reinforcement_management'] = 'simple';
            }
        }
        
        return $decision;
    }

    private function applySuboptimalDecision(array $decision, string $difficulty): array
    {
        $errorTypes = [
            'wrong_target',
            'poor_timing',
            'resource_waste',
            'troop_mismanagement'
        ];
        
        $errorType = $errorTypes[array_rand($errorTypes)];
        
        switch ($errorType) {
            case 'wrong_target':
                if (isset($decision['parameters']['target_preference'])) {
                    $decision['parameters']['target_preference'] = 'random';
                }
                break;
            
            case 'poor_timing':
                if (isset($decision['parameters']['execution_delay'])) {
                    $decision['parameters']['execution_delay'] *= 2;
                }
                break;
            
            case 'resource_waste':
                if (isset($decision['parameters']['resource_allocation'])) {
                    $decision['parameters']['resource_allocation'] *= 0.7;
                }
                break;
            
            case 'troop_mismanagement':
                if (isset($decision['parameters']['troop_ratio'])) {
                    $decision['parameters']['troop_ratio'] *= 0.6;
                }
                break;
        }
        
        $decision['error_type'] = $errorType;
        
        return $decision;
    }
}
