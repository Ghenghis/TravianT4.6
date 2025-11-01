<?php

namespace Services;

class PersonalityService
{
    private $personalityWeights = [
        'aggressive' => [
            'attack' => 0.40,
            'train' => 0.30,
            'farm' => 0.15,
            'build' => 0.10,
            'defend' => 0.05
        ],
        'economic' => [
            'build' => 0.40,
            'farm' => 0.25,
            'trade' => 0.20,
            'train' => 0.10,
            'defend' => 0.05
        ],
        'balanced' => [
            'build' => 0.25,
            'farm' => 0.25,
            'train' => 0.20,
            'attack' => 0.15,
            'defend' => 0.15
        ],
        'diplomat' => [
            'ally' => 0.30,
            'trade' => 0.25,
            'build' => 0.20,
            'defend' => 0.15,
            'farm' => 0.10
        ],
        'assassin' => [
            'scout' => 0.30,
            'attack' => 0.30,
            'hide' => 0.20,
            'train' => 0.15,
            'build' => 0.05
        ]
    ];

    private $personalityTraits = [
        'aggressive' => [
            'risk_tolerance' => 'high',
            'resource_threshold' => 'low',
            'attack_frequency' => 'high',
            'troop_usage_ratio' => 0.9,
            'expansion_priority' => 'high',
            'defense_priority' => 'low'
        ],
        'economic' => [
            'risk_tolerance' => 'low',
            'resource_threshold' => 'high',
            'attack_frequency' => 'low',
            'troop_usage_ratio' => 0.3,
            'expansion_priority' => 'medium',
            'defense_priority' => 'medium'
        ],
        'balanced' => [
            'risk_tolerance' => 'medium',
            'resource_threshold' => 'medium',
            'attack_frequency' => 'medium',
            'troop_usage_ratio' => 0.6,
            'expansion_priority' => 'medium',
            'defense_priority' => 'medium'
        ],
        'diplomat' => [
            'risk_tolerance' => 'low',
            'resource_threshold' => 'high',
            'attack_frequency' => 'very_low',
            'troop_usage_ratio' => 0.2,
            'expansion_priority' => 'low',
            'defense_priority' => 'high',
            'negotiation_priority' => 'high'
        ],
        'assassin' => [
            'risk_tolerance' => 'medium',
            'resource_threshold' => 'medium',
            'attack_frequency' => 'medium',
            'troop_usage_ratio' => 0.7,
            'expansion_priority' => 'low',
            'defense_priority' => 'medium',
            'stealth_priority' => 'high',
            'target_selectivity' => 'high'
        ]
    ];

    public function __construct()
    {
    }

    public function getPersonalityWeights(string $personality): array
    {
        $personality = strtolower($personality);
        
        if (!isset($this->personalityWeights[$personality])) {
            return $this->personalityWeights['balanced'];
        }
        
        return $this->personalityWeights[$personality];
    }

    public function applyPersonality(array $decision, string $personality): array
    {
        $personality = strtolower($personality);
        $traits = $this->getPersonalityTraits($personality);
        
        if (empty($traits)) {
            return $decision;
        }
        
        if (!isset($decision['parameters'])) {
            $decision['parameters'] = [];
        }
        
        switch ($personality) {
            case 'aggressive':
                $decision = $this->applyAggressiveTraits($decision, $traits);
                break;
            
            case 'economic':
                $decision = $this->applyEconomicTraits($decision, $traits);
                break;
            
            case 'balanced':
                $decision = $this->applyBalancedTraits($decision, $traits);
                break;
            
            case 'diplomat':
                $decision = $this->applyDiplomatTraits($decision, $traits);
                break;
            
            case 'assassin':
                $decision = $this->applyAssassinTraits($decision, $traits);
                break;
        }
        
        return $decision;
    }

    public function getPersonalityTraits(string $personality): array
    {
        $personality = strtolower($personality);
        
        if (!isset($this->personalityTraits[$personality])) {
            return $this->personalityTraits['balanced'];
        }
        
        return $this->personalityTraits[$personality];
    }

    public function selectActionByPersonality(string $personality, array $gameState): string
    {
        $weights = $this->getPersonalityWeights($personality);
        
        $availableActions = $this->filterAvailableActions($weights, $gameState);
        
        if (empty($availableActions)) {
            return 'idle';
        }
        
        return $this->weightedRandomSelect($availableActions);
    }

    private function applyAggressiveTraits(array $decision, array $traits): array
    {
        if ($decision['action'] === 'attack' || $decision['action'] === 'farm') {
            $decision['parameters']['troop_ratio'] = $traits['troop_usage_ratio'];
            $decision['parameters']['target_preference'] = 'weak';
            $decision['parameters']['min_success_chance'] = 0.6;
        }
        
        if ($decision['action'] === 'train') {
            $decision['parameters']['troop_type_preference'] = 'offensive';
            $decision['parameters']['resource_allocation'] = 0.8;
        }
        
        if ($decision['action'] === 'build') {
            $decision['parameters']['building_priority'] = 'military';
        }
        
        return $decision;
    }

    private function applyEconomicTraits(array $decision, array $traits): array
    {
        if ($decision['action'] === 'build') {
            $decision['parameters']['building_priority'] = 'resource';
            $decision['parameters']['upgrade_strategy'] = 'maximize_production';
        }
        
        if ($decision['action'] === 'farm') {
            $decision['parameters']['troop_ratio'] = $traits['troop_usage_ratio'];
            $decision['parameters']['target_preference'] = 'safe';
        }
        
        if ($decision['action'] === 'train') {
            $decision['parameters']['troop_type_preference'] = 'defensive';
            $decision['parameters']['resource_allocation'] = 0.4;
        }
        
        if ($decision['action'] === 'trade') {
            $decision['parameters']['trade_aggressiveness'] = 'high';
        }
        
        return $decision;
    }

    private function applyBalancedTraits(array $decision, array $traits): array
    {
        $decision['parameters']['troop_ratio'] = $traits['troop_usage_ratio'];
        $decision['parameters']['resource_allocation'] = 0.6;
        $decision['parameters']['strategy'] = 'balanced';
        
        return $decision;
    }

    private function applyDiplomatTraits(array $decision, array $traits): array
    {
        if ($decision['action'] === 'attack' || $decision['action'] === 'farm') {
            $decision['parameters']['troop_ratio'] = $traits['troop_usage_ratio'];
            $decision['parameters']['avoid_allies'] = true;
            $decision['parameters']['target_preference'] = 'very_safe';
        }
        
        if ($decision['action'] === 'trade') {
            $decision['parameters']['trade_aggressiveness'] = 'high';
            $decision['parameters']['favor_allies'] = true;
        }
        
        if ($decision['action'] === 'build') {
            $decision['parameters']['building_priority'] = 'defensive';
        }
        
        return $decision;
    }

    private function applyAssassinTraits(array $decision, array $traits): array
    {
        if ($decision['action'] === 'attack') {
            $decision['parameters']['troop_ratio'] = $traits['troop_usage_ratio'];
            $decision['parameters']['target_preference'] = 'high_value';
            $decision['parameters']['stealth_mode'] = true;
            $decision['parameters']['min_success_chance'] = 0.8;
        }
        
        if ($decision['action'] === 'scout') {
            $decision['parameters']['scout_frequency'] = 'high';
            $decision['parameters']['intelligence_priority'] = 'high';
        }
        
        if ($decision['action'] === 'train') {
            $decision['parameters']['troop_type_preference'] = 'scout_and_cavalry';
        }
        
        return $decision;
    }

    private function filterAvailableActions(array $weights, array $gameState): array
    {
        $available = [];
        
        foreach ($weights as $action => $weight) {
            if ($this->canPerformAction($action, $gameState)) {
                $available[$action] = $weight;
            }
        }
        
        return $available;
    }

    private function canPerformAction(string $action, array $gameState): bool
    {
        switch ($action) {
            case 'attack':
            case 'farm':
                return ($gameState['troops']['total'] ?? 0) > 10;
            
            case 'build':
                return ($gameState['resources']['total'] ?? 0) > 100;
            
            case 'train':
                return ($gameState['resources']['total'] ?? 0) > 500;
            
            case 'trade':
                return ($gameState['resources']['surplus'] ?? 0) > 1000;
            
            default:
                return true;
        }
    }

    private function weightedRandomSelect(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(0, (int)($totalWeight * 1000)) / 1000;
        
        $cumulativeWeight = 0;
        foreach ($weights as $action => $weight) {
            $cumulativeWeight += $weight;
            if ($random <= $cumulativeWeight) {
                return $action;
            }
        }
        
        return array_key_first($weights);
    }
}
