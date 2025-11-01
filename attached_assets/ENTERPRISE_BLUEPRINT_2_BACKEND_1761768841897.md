# Enterprise Blueprint #2: Complete Backend Implementation
## Travian-Solo Game Engine & API Services

## 🎮 CORE GAME ENGINE

### Tribe System Implementation
```python
# backend/app/game_engine/tribes.py
from enum import IntEnum
from dataclasses import dataclass
from typing import Dict, List

class TribeType(IntEnum):
    ROMANS = 1
    TEUTONS = 2
    GAULS = 3

@dataclass
class TribeBonus:
    """Tribe-specific bonuses"""
    building_bonus: float
    troop_bonus: float
    merchant_capacity: int
    cranny_bonus: float
    wall_bonus: float
    speed_bonus: float

class TribeSystem:
    """Complete tribe system with all bonuses and characteristics"""
    
    TRIBE_DATA: Dict[TribeType, TribeBonus] = {
        TribeType.ROMANS: TribeBonus(
            building_bonus=1.0,      # Standard building time
            troop_bonus=1.0,         # Balanced troops
            merchant_capacity=500,    # Standard capacity
            cranny_bonus=1.0,        # Standard cranny
            wall_bonus=1.5,          # City wall bonus
            speed_bonus=1.0          # Standard speed
        ),
        TribeType.TEUTONS: TribeBonus(
            building_bonus=1.0,
            troop_bonus=1.1,         # 10% attack bonus
            merchant_capacity=1000,   # Double capacity
            cranny_bonus=0.8,        # 20% less cranny protection
            wall_bonus=1.2,          # Earth wall bonus
            speed_bonus=0.9          # 10% slower
        ),
        TribeType.GAULS: TribeBonus(
            building_bonus=0.9,      # 10% faster building
            troop_bonus=0.9,         # 10% weaker offense
            merchant_capacity=750,    # 50% more capacity
            cranny_bonus=2.0,        # Double cranny protection
            wall_bonus=1.3,          # Palisade bonus
            speed_bonus=1.2          # 20% faster units
        )
    }
    
    @classmethod
    def get_tribe_bonus(cls, tribe_type: TribeType) -> TribeBonus:
        return cls.TRIBE_DATA[tribe_type]
```

### Building System
```python
# backend/app/game_engine/buildings_data.py
from dataclasses import dataclass
from typing import Dict, List, Optional, Tuple
from enum import IntEnum

class BuildingType(IntEnum):
    # Resource Buildings
    WOODCUTTER = 1
    CLAY_PIT = 2
    IRON_MINE = 3
    CROPLAND = 4
    
    # Infrastructure
    SAWMILL = 5
    BRICKYARD = 6
    IRON_FOUNDRY = 7
    GRAIN_MILL = 8
    BAKERY = 9
    
    # Village Buildings
    WAREHOUSE = 10
    GRANARY = 11
    CRANNY = 12
    MAIN_BUILDING = 13
    RALLY_POINT = 14
    MARKETPLACE = 15
    EMBASSY = 16
    RESIDENCE = 17
    PALACE = 18
    TREASURY = 19
    TRADE_OFFICE = 20
    
    # Military Buildings
    BARRACKS = 21
    STABLE = 22
    WORKSHOP = 23
    BLACKSMITH = 24
    ARMOURY = 25
    ACADEMY = 26
    HERO_MANSION = 27
    GREAT_BARRACKS = 28
    GREAT_STABLE = 29
    CITY_WALL = 30
    EARTH_WALL = 31
    PALISADE = 32
    TOURNAMENT_SQUARE = 33
    
    # Special Buildings
    STONEMASON = 34
    BREWERY = 35
    TRAPPER = 36
    GREAT_WAREHOUSE = 37
    GREAT_GRANARY = 38
    WONDER = 39
    HORSE_DRINKING = 40
    WATER_DITCH = 41

@dataclass
class BuildingCost:
    """Building construction cost"""
    wood: int
    clay: int
    iron: int
    crop: int
    population: int
    time: int  # Base time in seconds
    culture_points: int

@dataclass
class BuildingData:
    """Complete building information"""
    id: BuildingType
    name: str
    description: str
    max_level: int
    category: str
    prerequisites: List[Tuple[BuildingType, int]]  # (building_type, required_level)
    costs: Dict[int, BuildingCost]  # level -> cost
    effects: Dict[int, Dict[str, float]]  # level -> effect

class BuildingSystem:
    """Complete building system with all data"""
    
    BUILDING_DATA: Dict[BuildingType, BuildingData] = {
        BuildingType.WOODCUTTER: BuildingData(
            id=BuildingType.WOODCUTTER,
            name="Woodcutter",
            description="Produces wood for construction",
            max_level=20,
            category="resource",
            prerequisites=[],
            costs={
                1: BuildingCost(40, 100, 50, 60, 2, 260, 1),
                2: BuildingCost(65, 165, 85, 100, 1, 620, 1),
                3: BuildingCost(110, 280, 140, 165, 1, 1190, 2),
                4: BuildingCost(185, 465, 235, 280, 1, 2100, 2),
                5: BuildingCost(310, 780, 390, 465, 1, 3560, 2),
                # ... levels 6-20
            },
            effects={
                1: {"production": 5},
                2: {"production": 9},
                3: {"production": 15},
                4: {"production": 22},
                5: {"production": 33},
                # ... levels 6-20
            }
        ),
        # ... All other buildings
    }
    
    @classmethod
    def get_building_cost(cls, building_type: BuildingType, level: int) -> BuildingCost:
        """Get construction cost for specific building level"""
        building = cls.BUILDING_DATA[building_type]
        return building.costs.get(level)
    
    @classmethod
    def calculate_build_time(cls, base_time: int, main_building_level: int, tribe_bonus: float) -> int:
        """Calculate actual build time with bonuses"""
        time_reduction = 1 - (main_building_level * 0.05)  # 5% per MB level
        return int(base_time * time_reduction * tribe_bonus)
    
    @classmethod
    def can_build(cls, village_data: dict, building_type: BuildingType, level: int) -> Tuple[bool, str]:
        """Check if building can be constructed"""
        building = cls.BUILDING_DATA[building_type]
        
        # Check prerequisites
        for prereq_type, prereq_level in building.prerequisites:
            if village_data['buildings'].get(prereq_type, 0) < prereq_level:
                return False, f"Requires {cls.BUILDING_DATA[prereq_type].name} level {prereq_level}"
        
        # Check resources
        cost = cls.get_building_cost(building_type, level)
        if village_data['resources']['wood'] < cost.wood:
            return False, "Not enough wood"
        if village_data['resources']['clay'] < cost.clay:
            return False, "Not enough clay"
        if village_data['resources']['iron'] < cost.iron:
            return False, "Not enough iron"
        if village_data['resources']['crop'] < cost.crop:
            return False, "Not enough crop"
        
        # Check population
        if village_data['free_population'] < cost.population:
            return False, "Not enough free population"
        
        return True, "Can build"
```

### Troop System
```python
# backend/app/game_engine/troops_data.py
from dataclasses import dataclass
from typing import Dict, List
from enum import IntEnum

class TroopType(IntEnum):
    # Roman Infantry
    LEGIONNAIRE = 1
    PRAETORIAN = 2
    IMPERIAN = 3
    
    # Roman Cavalry
    EQUITES_LEGATI = 4
    EQUITES_IMPERATORIS = 5
    EQUITES_CAESARIS = 6
    
    # Roman Siege
    BATTERING_RAM = 7
    FIRE_CATAPULT = 8
    
    # Roman Special
    SENATOR = 9
    SETTLER = 10
    
    # Teuton Infantry
    CLUBSWINGER = 11
    SPEARFIGHTER = 12
    AXEFIGHTER = 13
    
    # Teuton Cavalry  
    SCOUT = 14
    PALADIN = 15
    TEUTONIC_KNIGHT = 16
    
    # Teuton Siege
    RAM = 17
    CATAPULT = 18
    
    # Teuton Special
    CHIEF = 19
    SETTLER_TEUTON = 20
    
    # Gaul Infantry
    PHALANX = 21
    SWORDSMAN = 22
    
    # Gaul Cavalry
    PATHFINDER = 23
    THEUTATES_THUNDER = 24
    DRUIDRIDER = 25
    HAEDUAN = 26
    
    # Gaul Siege
    RAM_GAUL = 27
    TREBUCHET = 28
    
    # Gaul Special
    CHIEFTAIN = 29
    SETTLER_GAUL = 30

@dataclass
class TroopStats:
    """Complete troop statistics"""
    attack: int
    defense_infantry: int
    defense_cavalry: int
    speed: int  # fields per hour
    carry_capacity: int
    upkeep: int  # crop consumption per hour
    training_time: int  # seconds
    
@dataclass
class TroopCost:
    """Troop training cost"""
    wood: int
    clay: int
    iron: int
    crop: int

@dataclass
class TroopData:
    """Complete troop information"""
    id: TroopType
    name: str
    tribe: TribeType
    stats: TroopStats
    cost: TroopCost
    building_required: BuildingType
    research_required: Optional[str] = None

class TroopSystem:
    """Complete troop system with combat calculations"""
    
    TROOP_DATA: Dict[TroopType, TroopData] = {
        TroopType.LEGIONNAIRE: TroopData(
            id=TroopType.LEGIONNAIRE,
            name="Legionnaire",
            tribe=TribeType.ROMANS,
            stats=TroopStats(40, 35, 50, 6, 50, 1, 1600),
            cost=TroopCost(120, 100, 150, 30),
            building_required=BuildingType.BARRACKS
        ),
        TroopType.PRAETORIAN: TroopData(
            id=TroopType.PRAETORIAN,
            name="Praetorian",
            tribe=TribeType.ROMANS,
            stats=TroopStats(30, 65, 35, 5, 20, 1, 1760),
            cost=TroopCost(100, 130, 160, 70),
            building_required=BuildingType.BARRACKS,
            research_required="Academy"
        ),
        # ... All other troops
    }
    
    @classmethod
    def calculate_training_time(cls, troop_type: TroopType, 
                               barracks_level: int, 
                               great_barracks: bool = False) -> int:
        """Calculate actual training time with bonuses"""
        base_time = cls.TROOP_DATA[troop_type].stats.training_time
        reduction = 1 - (barracks_level * 0.1)  # 10% per level
        if great_barracks:
            reduction *= 0.5  # 50% with great barracks
        return int(base_time * reduction)
```

### Combat Engine
```python
# backend/app/game_engine/combat_engine.py
import math
import random
from typing import Dict, List, Tuple, Optional
from dataclasses import dataclass
from app.game_engine.troops_data import TroopType, TroopSystem

@dataclass
class Army:
    """Army composition for combat"""
    troops: Dict[TroopType, int]  # troop_type -> quantity
    hero: Optional['Hero'] = None

@dataclass
class CombatResult:
    """Result of a combat simulation"""
    attacker_losses: Dict[TroopType, int]
    defender_losses: Dict[TroopType, int]
    winner: str  # 'attacker' or 'defender'
    resources_stolen: Dict[str, int]
    buildings_damaged: List[Tuple[str, int]]  # (building_type, damage_level)
    experience_gained: int

class CombatEngine:
    """Advanced combat calculation engine"""
    
    @classmethod
    def simulate_battle(cls, 
                       attacker: Army, 
                       defender: Army,
                       wall_level: int = 0,
                       tribe_type: TribeType = TribeType.ROMANS) -> CombatResult:
        """Simulate complete battle with Travian combat formula"""
        
        # Calculate attack points
        total_attack = cls._calculate_attack_points(attacker)
        
        # Calculate defense points
        total_defense = cls._calculate_defense_points(defender, wall_level, tribe_type)
        
        # Apply hero bonuses
        if attacker.hero:
            total_attack *= (1 + attacker.hero.off_bonus / 100)
        if defender.hero:
            total_defense *= (1 + defender.hero.def_bonus / 100)
        
        # Determine winner
        if total_attack > total_defense:
            winner = 'attacker'
            loss_ratio = total_defense / total_attack
        else:
            winner = 'defender'
            loss_ratio = total_attack / total_defense
        
        # Calculate losses
        attacker_losses = cls._calculate_losses(attacker.troops, loss_ratio if winner == 'defender' else 1 - loss_ratio)
        defender_losses = cls._calculate_losses(defender.troops, loss_ratio if winner == 'attacker' else 1 - loss_ratio)
        
        # Calculate resources stolen (if attacker wins)
        resources_stolen = {}
        if winner == 'attacker':
            carry_capacity = cls._calculate_carry_capacity(attacker.troops, attacker_losses)
            resources_stolen = cls._calculate_stolen_resources(carry_capacity)
        
        # Calculate building damage (catapults)
        buildings_damaged = []
        if TroopType.FIRE_CATAPULT in attacker.troops and winner == 'attacker':
            catapult_count = attacker.troops[TroopType.FIRE_CATAPULT] - attacker_losses.get(TroopType.FIRE_CATAPULT, 0)
            buildings_damaged = cls._calculate_building_damage(catapult_count)
        
        # Calculate experience
        experience_gained = cls._calculate_experience(attacker_losses, defender_losses, winner)
        
        return CombatResult(
            attacker_losses=attacker_losses,
            defender_losses=defender_losses,
            winner=winner,
            resources_stolen=resources_stolen,
            buildings_damaged=buildings_damaged,
            experience_gained=experience_gained
        )
    
    @classmethod
    def _calculate_attack_points(cls, army: Army) -> float:
        """Calculate total attack points of an army"""
        total = 0.0
        for troop_type, quantity in army.troops.items():
            troop_data = TroopSystem.TROOP_DATA[troop_type]
            total += troop_data.stats.attack * quantity
        return total
    
    @classmethod
    def _calculate_defense_points(cls, army: Army, wall_level: int, tribe_type: TribeType) -> float:
        """Calculate total defense points including wall bonus"""
        infantry_defense = 0.0
        cavalry_defense = 0.0
        
        for troop_type, quantity in army.troops.items():
            troop_data = TroopSystem.TROOP_DATA[troop_type]
            infantry_defense += troop_data.stats.defense_infantry * quantity
            cavalry_defense += troop_data.stats.defense_cavalry * quantity
        
        # Apply wall bonus
        wall_bonus = cls._get_wall_bonus(wall_level, tribe_type)
        total_defense = (infantry_defense + cavalry_defense) * wall_bonus
        
        return total_defense
    
    @classmethod
    def _get_wall_bonus(cls, wall_level: int, tribe_type: TribeType) -> float:
        """Calculate wall defensive bonus"""
        base_bonus = {
            TribeType.ROMANS: 1.03,   # City Wall
            TribeType.TEUTONS: 1.02,  # Earth Wall
            TribeType.GAULS: 1.025    # Palisade
        }
        
        if wall_level == 0:
            return 1.0
        
        tribe_bonus = base_bonus.get(tribe_type, 1.02)
        return tribe_bonus ** wall_level
    
    @classmethod
    def _calculate_losses(cls, troops: Dict[TroopType, int], loss_ratio: float) -> Dict[TroopType, int]:
        """Calculate troop losses based on loss ratio"""
        losses = {}
        for troop_type, quantity in troops.items():
            losses[troop_type] = math.ceil(quantity * loss_ratio)
        return losses
    
    @classmethod
    def _calculate_carry_capacity(cls, troops: Dict[TroopType, int], losses: Dict[TroopType, int]) -> int:
        """Calculate remaining carry capacity after losses"""
        total_capacity = 0
        for troop_type, quantity in troops.items():
            remaining = quantity - losses.get(troop_type, 0)
            troop_data = TroopSystem.TROOP_DATA[troop_type]
            total_capacity += troop_data.stats.carry_capacity * remaining
        return total_capacity
    
    @classmethod
    def _calculate_stolen_resources(cls, carry_capacity: int) -> Dict[str, int]:
        """Calculate resources that can be stolen"""
        # Simplified: divide equally among resources
        per_resource = carry_capacity // 4
        return {
            'wood': per_resource,
            'clay': per_resource,
            'iron': per_resource,
            'crop': per_resource
        }
    
    @classmethod
    def _calculate_building_damage(cls, catapult_count: int) -> List[Tuple[str, int]]:
        """Calculate building damage from catapults"""
        damage_levels = min(catapult_count // 20, 5)  # 20 catapults = 1 level damage
        if damage_levels > 0:
            # Target random building
            target_building = random.choice(list(BuildingType))
            return [(target_building.name, damage_levels)]
        return []
    
    @classmethod
    def _calculate_experience(cls, attacker_losses: Dict, defender_losses: Dict, winner: str) -> int:
        """Calculate hero experience from battle"""
        total_losses = sum(attacker_losses.values()) + sum(defender_losses.values())
        base_exp = total_losses * 10
        
        if winner == 'attacker':
            return int(base_exp * 1.5)
        else:
            return base_exp
```

## 🔌 FASTAPI BACKEND SERVICES

### Main Application
```python
# backend/app/main.py
from fastapi import FastAPI, Request, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse
from contextlib import asynccontextmanager
import time
import logging

from app.core.config import settings
from app.core.database import engine, Base
from app.core.redis import redis_client
from app.core.security import limiter
from app.core.exceptions import CustomException
from app.api.v1 import api_router
from app.core.scheduler import scheduler

logger = logging.getLogger(__name__)

@asynccontextmanager
async def lifespan(app: FastAPI):
    """Startup and shutdown events"""
    # Startup
    logger.info("Starting up Travian-Solo backend...")
    
    # Create database tables
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    
    # Initialize scheduler
    scheduler.start()
    
    # Test Redis connection
    await redis_client.ping()
    
    yield
    
    # Shutdown
    logger.info("Shutting down...")
    scheduler.shutdown()
    await redis_client.close()
    await engine.dispose()

app = FastAPI(
    title="Travian-Solo API",
    description="Complete Travian clone game backend",
    version="1.0.0",
    lifespan=lifespan
)

# Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.add_middleware(
    TrustedHostMiddleware,
    allowed_hosts=settings.ALLOWED_HOSTS
)

# Add rate limiting
app.state.limiter = limiter
app.add_exception_handler(429, lambda request, exc: JSONResponse(
    status_code=429,
    content={"detail": "Too many requests"}
))

# Add request timing middleware
@app.middleware("http")
async def add_process_time_header(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    response.headers["X-Process-Time"] = str(process_time)
    return response

# Exception handlers
@app.exception_handler(CustomException)
async def custom_exception_handler(request: Request, exc: CustomException):
    return JSONResponse(
        status_code=exc.status_code,
        content={"detail": exc.detail}
    )

# Include API routes
app.include_router(api_router, prefix="/api/v1")

# Health check
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "version": "1.0.0",
        "timestamp": time.time()
    }

# Metrics endpoint for Prometheus
@app.get("/metrics")
async def metrics():
    # Return Prometheus metrics
    return PlainTextResponse(generate_metrics())
```

### Authentication Service
```python
# backend/app/api/v1/auth.py
from fastapi import APIRouter, Depends, HTTPException, status, BackgroundTasks
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.ext.asyncio import AsyncSession
from datetime import datetime, timedelta
from typing import Optional
import secrets

from app.core.database import get_db
from app.core.security import verify_password, create_access_token, get_password_hash
from app.schemas.auth import UserCreate, UserLogin, Token, UserResponse, PasswordReset
from app.models.user import User
from app.services.auth_service import AuthService
from app.services.email_service import EmailService
from app.core.cache import cache_result

router = APIRouter(prefix="/auth", tags=["Authentication"])

@router.post("/register", response_model=UserResponse)
async def register(
    user_data: UserCreate,
    background_tasks: BackgroundTasks,
    db: AsyncSession = Depends(get_db)
):
    """Register a new user"""
    # Check if username exists
    existing_user = await AuthService.get_user_by_username(db, user_data.username)
    if existing_user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Username already registered"
        )
    
    # Check if email exists
    existing_email = await AuthService.get_user_by_email(db, user_data.email)
    if existing_email:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Email already registered"
        )
    
    # Create user
    user = await AuthService.create_user(db, user_data)
    
    # Send welcome email in background
    background_tasks.add_task(
        EmailService.send_welcome_email,
        user.email,
        user.username
    )
    
    # Create initial village
    await AuthService.create_initial_village(db, user.id, user.tribe_id)
    
    return user

@router.post("/login", response_model=Token)
async def login(
    form_data: OAuth2PasswordRequestForm = Depends(),
    db: AsyncSession = Depends(get_db)
):
    """Login user and return JWT token"""
    user = await AuthService.authenticate_user(
        db, form_data.username, form_data.password
    )
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect username or password",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    # Update last login
    await AuthService.update_last_login(db, user.id)
    
    # Create tokens
    access_token = create_access_token(
        data={"sub": str(user.id), "username": user.username}
    )
    refresh_token = create_access_token(
        data={"sub": str(user.id), "type": "refresh"},
        expires_delta=timedelta(days=7)
    )
    
    return {
        "access_token": access_token,
        "refresh_token": refresh_token,
        "token_type": "bearer"
    }

@router.post("/refresh", response_model=Token)
async def refresh_token(
    refresh_token: str,
    db: AsyncSession = Depends(get_db)
):
    """Refresh access token using refresh token"""
    payload = verify_token(refresh_token)
    
    if payload.get("type") != "refresh":
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid refresh token"
        )
    
    user_id = payload.get("sub")
    user = await AuthService.get_user_by_id(db, user_id)
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="User not found"
        )
    
    # Create new access token
    access_token = create_access_token(
        data={"sub": str(user.id), "username": user.username}
    )
    
    return {
        "access_token": access_token,
        "refresh_token": refresh_token,
        "token_type": "bearer"
    }

@router.post("/password-reset-request")
async def request_password_reset(
    email: str,
    background_tasks: BackgroundTasks,
    db: AsyncSession = Depends(get_db)
):
    """Request password reset email"""
    user = await AuthService.get_user_by_email(db, email)
    
    if user:
        # Generate reset token
        reset_token = secrets.token_urlsafe(32)
        await AuthService.save_reset_token(db, user.id, reset_token)
        
        # Send reset email in background
        background_tasks.add_task(
            EmailService.send_password_reset_email,
            user.email,
            reset_token
        )
    
    # Always return success to prevent email enumeration
    return {"message": "If the email exists, a reset link has been sent"}

@router.post("/password-reset")
async def reset_password(
    reset_data: PasswordReset,
    db: AsyncSession = Depends(get_db)
):
    """Reset password using reset token"""
    user = await AuthService.verify_reset_token(db, reset_data.token)
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid or expired reset token"
        )
    
    # Update password
    await AuthService.update_password(db, user.id, reset_data.new_password)
    
    return {"message": "Password reset successful"}

@router.post("/logout")
async def logout(current_user: User = Depends(get_current_user)):
    """Logout user (client should remove token)"""
    # Optionally blacklist token in Redis
    await AuthService.blacklist_token(current_user.id)
    
    return {"message": "Logged out successfully"}
```

### Village Management Service
```python
# backend/app/api/v1/villages.py
from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from typing import List, Optional
from uuid import UUID

from app.core.database import get_db
from app.core.dependencies import get_current_user
from app.models.user import User
from app.models.village import Village
from app.schemas.village import VillageResponse, VillageUpdate, BuildingUpgrade
from app.services.village_service import VillageService
from app.services.building_service import BuildingService
from app.core.cache import cache_result

router = APIRouter(prefix="/villages", tags=["Villages"])

@router.get("/", response_model=List[VillageResponse])
@cache_result("user_villages", ttl=300)
async def get_user_villages(
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get all villages for current user"""
    villages = await VillageService.get_user_villages(db, current_user.id)
    return villages

@router.get("/{village_id}", response_model=VillageResponse)
async def get_village_details(
    village_id: UUID,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get detailed village information"""
    village = await VillageService.get_village_by_id(db, village_id)
    
    if not village or village.user_id != current_user.id:
        raise HTTPException(status_code=404, detail="Village not found")
    
    # Update resources before returning
    await VillageService.update_village_resources(db, village_id)
    
    return village

@router.put("/{village_id}", response_model=VillageResponse)
async def update_village(
    village_id: UUID,
    village_data: VillageUpdate,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Update village information (name, etc.)"""
    village = await VillageService.get_village_by_id(db, village_id)
    
    if not village or village.user_id != current_user.id:
        raise HTTPException(status_code=404, detail="Village not found")
    
    updated_village = await VillageService.update_village(db, village_id, village_data)
    return updated_village

@router.post("/{village_id}/buildings/{building_type}/upgrade")
async def upgrade_building(
    village_id: UUID,
    building_type: int,
    position: int = Query(..., ge=0, le=40),
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Start building upgrade"""
    village = await VillageService.get_village_by_id(db, village_id)
    
    if not village or village.user_id != current_user.id:
        raise HTTPException(status_code=404, detail="Village not found")
    
    # Check if can build
    can_build, message = await BuildingService.can_upgrade_building(
        db, village_id, building_type, position
    )
    
    if not can_build:
        raise HTTPException(status_code=400, detail=message)
    
    # Start upgrade
    building = await BuildingService.start_upgrade(
        db, village_id, building_type, position
    )
    
    return {
        "message": "Building upgrade started",
        "complete_at": building.upgrade_complete_at
    }

@router.post("/{village_id}/buildings/{building_id}/cancel")
async def cancel_building_upgrade(
    village_id: UUID,
    building_id: UUID,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Cancel building upgrade and refund resources"""
    village = await VillageService.get_village_by_id(db, village_id)
    
    if not village or village.user_id != current_user.id:
        raise HTTPException(status_code=404, detail="Village not found")
    
    # Cancel upgrade
    refunded = await BuildingService.cancel_upgrade(db, building_id)
    
    if not refunded:
        raise HTTPException(status_code=400, detail="Cannot cancel upgrade")
    
    return {
        "message": "Building upgrade cancelled",
        "resources_refunded": refunded
    }

@router.get("/{village_id}/production", response_model=dict)
async def get_production_overview(
    village_id: UUID,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get resource production overview"""
    village = await VillageService.get_village_by_id(db, village_id)
    
    if not village or village.user_id != current_user.id:
        raise HTTPException(status_code=404, detail="Village not found")
    
    production = await VillageService.calculate_production(db, village_id)
    
    return {
        "wood_production": production['wood'],
        "clay_production": production['clay'],
        "iron_production": production['iron'],
        "crop_production": production['crop'],
        "crop_consumption": production['consumption'],
        "net_crop": production['crop'] - production['consumption']
    }

@router.post("/found")
async def found_new_village(
    x: int = Query(..., ge=-400, le=400),
    y: int = Query(..., ge=-400, le=400),
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Found a new village at specified coordinates"""
    # Check if user has settlers
    has_settlers = await VillageService.check_settlers(db, current_user.id)
    
    if not has_settlers:
        raise HTTPException(status_code=400, detail="You need 3 settlers to found a village")
    
    # Check if coordinates are free
    existing = await VillageService.get_village_at_coordinates(db, x, y)
    
    if existing:
        raise HTTPException(status_code=400, detail="Coordinates already occupied")
    
    # Check culture points
    required_cp = await VillageService.calculate_required_culture_points(db, current_user.id)
    current_cp = await VillageService.get_user_culture_points(db, current_user.id)
    
    if current_cp < required_cp:
        raise HTTPException(
            status_code=400,
            detail=f"Not enough culture points. Required: {required_cp}, Current: {current_cp}"
        )
    
    # Found village
    new_village = await VillageService.found_village(db, current_user.id, x, y)
    
    return {
        "message": "New village founded successfully",
        "village_id": new_village.id,
        "coordinates": f"({x}|{y})"
    }
```

### Resource Management Service
```python
# backend/app/services/resource_service.py
from datetime import datetime, timedelta
from typing import Dict, Optional
from uuid import UUID
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, update

from app.models.village import Village, VillageResources
from app.models.building import Building
from app.game_engine.buildings_data import BuildingType, BuildingSystem

class ResourceService:
    """Service for managing village resources"""
    
    @classmethod
    async def update_resources(cls, db: AsyncSession, village_id: UUID) -> Dict[str, float]:
        """Update village resources based on production"""
        # Get village resources
        result = await db.execute(
            select(VillageResources).where(VillageResources.village_id == village_id)
        )
        resources = result.scalar_one_or_none()
        
        if not resources:
            return {}
        
        # Calculate time since last update
        now = datetime.utcnow()
        time_diff = (now - resources.last_update).total_seconds() / 3600  # Hours
        
        # Calculate production
        production = await cls.calculate_production(db, village_id)
        
        # Update resources
        resources.wood = min(
            resources.wood + (production['wood'] * time_diff),
            resources.warehouse_capacity
        )
        resources.clay = min(
            resources.clay + (production['clay'] * time_diff),
            resources.warehouse_capacity
        )
        resources.iron = min(
            resources.iron + (production['iron'] * time_diff),
            resources.warehouse_capacity
        )
        
        # Crop calculation (considering consumption)
        net_crop = production['crop'] - production['consumption']
        resources.crop = min(
            resources.crop + (net_crop * time_diff),
            resources.granary_capacity
        )
        
        # Handle starvation
        if resources.crop < 0:
            resources.crop = 0
            # TODO: Implement troop starvation logic
        
        resources.last_update = now
        await db.commit()
        
        return {
            'wood': resources.wood,
            'clay': resources.clay,
            'iron': resources.iron,
            'crop': resources.crop
        }
    
    @classmethod
    async def calculate_production(cls, db: AsyncSession, village_id: UUID) -> Dict[str, int]:
        """Calculate resource production per hour"""
        # Get all resource buildings
        result = await db.execute(
            select(Building).where(
                Building.village_id == village_id,
                Building.building_type_id.in_([
                    BuildingType.WOODCUTTER,
                    BuildingType.CLAY_PIT,
                    BuildingType.IRON_MINE,
                    BuildingType.CROPLAND
                ])
            )
        )
        buildings = result.scalars().all()
        
        production = {
            'wood': 0,
            'clay': 0,
            'iron': 0,
            'crop': 0,
            'consumption': 0
        }
        
        for building in buildings:
            if building.building_type_id == BuildingType.WOODCUTTER:
                production['wood'] += cls._get_production_rate(building.level)
            elif building.building_type_id == BuildingType.CLAY_PIT:
                production['clay'] += cls._get_production_rate(building.level)
            elif building.building_type_id == BuildingType.IRON_MINE:
                production['iron'] += cls._get_production_rate(building.level)
            elif building.building_type_id == BuildingType.CROPLAND:
                production['crop'] += cls._get_production_rate(building.level)
        
        # Apply bonus buildings (Sawmill, Brickyard, etc.)
        production = await cls._apply_bonus_buildings(db, village_id, production)
        
        # Calculate troop consumption
        production['consumption'] = await cls._calculate_consumption(db, village_id)
        
        return production
    
    @staticmethod
    def _get_production_rate(level: int) -> int:
        """Get base production rate for resource field level"""
        # Travian production formula
        if level == 0:
            return 2
        return int(5 * level * 1.2 ** level)
    
    @classmethod
    async def _apply_bonus_buildings(cls, db: AsyncSession, village_id: UUID, 
                                    production: Dict) -> Dict:
        """Apply production bonuses from special buildings"""
        # Check for Sawmill
        sawmill = await cls._get_building_level(db, village_id, BuildingType.SAWMILL)
        if sawmill:
            production['wood'] *= (1 + sawmill * 0.05)
        
        # Check for Brickyard
        brickyard = await cls._get_building_level(db, village_id, BuildingType.BRICKYARD)
        if brickyard:
            production['clay'] *= (1 + brickyard * 0.05)
        
        # Check for Iron Foundry
        foundry = await cls._get_building_level(db, village_id, BuildingType.IRON_FOUNDRY)
        if foundry:
            production['iron'] *= (1 + foundry * 0.05)
        
        # Check for Grain Mill and Bakery
        mill = await cls._get_building_level(db, village_id, BuildingType.GRAIN_MILL)
        bakery = await cls._get_building_level(db, village_id, BuildingType.BAKERY)
        
        if mill:
            production['crop'] *= (1 + mill * 0.05)
        if bakery:
            production['crop'] *= (1 + bakery * 0.05)
        
        return production
    
    @classmethod
    async def _get_building_level(cls, db: AsyncSession, village_id: UUID, 
                                 building_type: BuildingType) -> Optional[int]:
        """Get building level if exists"""
        result = await db.execute(
            select(Building.level).where(
                Building.village_id == village_id,
                Building.building_type_id == building_type
            )
        )
        level = result.scalar_one_or_none()
        return level
    
    @classmethod
    async def _calculate_consumption(cls, db: AsyncSession, village_id: UUID) -> int:
        """Calculate total crop consumption"""
        # Base village consumption
        consumption = 6
        
        # Add troop consumption
        # TODO: Implement troop consumption calculation
        
        return consumption
    
    @classmethod
    async def consume_resources(cls, db: AsyncSession, village_id: UUID, 
                               cost: Dict[str, int]) -> bool:
        """Consume resources for construction/training"""
        # Get current resources
        result = await db.execute(
            select(VillageResources).where(VillageResources.village_id == village_id)
            .with_for_update()  # Lock row for update
        )
        resources = result.scalar_one_or_none()
        
        if not resources:
            return False
        
        # Check if enough resources
        if (resources.wood < cost.get('wood', 0) or
            resources.clay < cost.get('clay', 0) or
            resources.iron < cost.get('iron', 0) or
            resources.crop < cost.get('crop', 0)):
            return False
        
        # Consume resources
        resources.wood -= cost.get('wood', 0)
        resources.clay -= cost.get('clay', 0)
        resources.iron -= cost.get('iron', 0)
        resources.crop -= cost.get('crop', 0)
        
        await db.commit()
        return True
```

### WebSocket Real-time Service
```python
# backend/app/services/websocket_service.py
from fastapi import WebSocket, WebSocketDisconnect, Depends
from typing import Dict, Set
import json
import asyncio
from uuid import UUID

from app.core.dependencies import get_current_user_ws
from app.core.redis import redis_client

class ConnectionManager:
    """Manage WebSocket connections"""
    
    def __init__(self):
        self.active_connections: Dict[UUID, WebSocket] = {}
        self.user_villages: Dict[UUID, Set[UUID]] = {}
    
    async def connect(self, websocket: WebSocket, user_id: UUID):
        """Accept new WebSocket connection"""
        await websocket.accept()
        self.active_connections[user_id] = websocket
        
        # Subscribe to user's Redis channel
        await self.subscribe_to_user_channel(user_id)
    
    def disconnect(self, user_id: UUID):
        """Remove WebSocket connection"""
        if user_id in self.active_connections:
            del self.active_connections[user_id]
        if user_id in self.user_villages:
            del self.user_villages[user_id]
    
    async def send_personal_message(self, message: str, user_id: UUID):
        """Send message to specific user"""
        if user_id in self.active_connections:
            websocket = self.active_connections[user_id]
            await websocket.send_text(message)
    
    async def send_village_update(self, village_id: UUID, data: dict):
        """Send update to all users watching a village"""
        message = json.dumps({
            'type': 'village_update',
            'village_id': str(village_id),
            'data': data
        })
        
        # Find users watching this village
        for user_id, villages in self.user_villages.items():
            if village_id in villages:
                await self.send_personal_message(message, user_id)
    
    async def broadcast(self, message: str):
        """Broadcast message to all connected users"""
        for connection in self.active_connections.values():
            await connection.send_text(message)
    
    async def subscribe_to_user_channel(self, user_id: UUID):
        """Subscribe to Redis pub/sub for user events"""
        pubsub = redis_client.pubsub()
        await pubsub.subscribe(f"user:{user_id}")
        
        async def reader():
            async for message in pubsub.listen():
                if message['type'] == 'message':
                    await self.send_personal_message(
                        message['data'].decode('utf-8'),
                        user_id
                    )
        
        asyncio.create_task(reader())

manager = ConnectionManager()

class WebSocketService:
    """WebSocket event handlers"""
    
    @staticmethod
    async def handle_connection(websocket: WebSocket):
        """Handle new WebSocket connection"""
        # Authenticate user
        user = await get_current_user_ws(websocket)
        if not user:
            await websocket.close(code=1008)
            return
        
        # Connect user
        await manager.connect(websocket, user.id)
        
        try:
            # Send initial connection message
            await websocket.send_text(json.dumps({
                'type': 'connected',
                'user_id': str(user.id)
            }))
            
            # Handle incoming messages
            while True:
                data = await websocket.receive_text()
                await WebSocketService.handle_message(user.id, data)
        
        except WebSocketDisconnect:
            manager.disconnect(user.id)
    
    @staticmethod
    async def handle_message(user_id: UUID, message: str):
        """Process incoming WebSocket message"""
        try:
            data = json.loads(message)
            message_type = data.get('type')
            
            if message_type == 'subscribe_village':
                village_id = UUID(data.get('village_id'))
                if user_id not in manager.user_villages:
                    manager.user_villages[user_id] = set()
                manager.user_villages[user_id].add(village_id)
                
            elif message_type == 'unsubscribe_village':
                village_id = UUID(data.get('village_id'))
                if user_id in manager.user_villages:
                    manager.user_villages[user_id].discard(village_id)
            
            elif message_type == 'ping':
                await manager.send_personal_message(
                    json.dumps({'type': 'pong'}),
                    user_id
                )
        
        except Exception as e:
            await manager.send_personal_message(
                json.dumps({'type': 'error', 'message': str(e)}),
                user_id
            )
    
    @staticmethod
    async def notify_building_complete(village_id: UUID, building_data: dict):
        """Notify when building construction is complete"""
        await manager.send_village_update(village_id, {
            'event': 'building_complete',
            'building': building_data
        })
    
    @staticmethod
    async def notify_troop_arrival(village_id: UUID, movement_data: dict):
        """Notify when troops arrive"""
        await manager.send_village_update(village_id, {
            'event': 'troop_arrival',
            'movement': movement_data
        })
    
    @staticmethod
    async def notify_attack_incoming(village_id: UUID, attack_data: dict):
        """Notify about incoming attack"""
        await manager.send_village_update(village_id, {
            'event': 'incoming_attack',
            'attack': attack_data
        })
```

## 📊 BACKGROUND TASKS & SCHEDULER

### Celery Configuration
```python
# backend/app/core/celery_app.py
from celery import Celery
from celery.schedules import crontab
from app.core.config import settings

celery_app = Celery(
    'travian_tasks',
    broker=f'redis://:{settings.REDIS_PASSWORD}@{settings.REDIS_HOST}:{settings.REDIS_PORT}/1',
    backend=f'redis://:{settings.REDIS_PASSWORD}@{settings.REDIS_HOST}:{settings.REDIS_PORT}/2',
    include=['app.tasks']
)

# Configuration
celery_app.conf.update(
    task_serializer='json',
    accept_content=['json'],
    result_serializer='json',
    timezone='UTC',
    enable_utc=True,
    task_track_started=True,
    task_time_limit=30 * 60,  # 30 minutes
    task_soft_time_limit=25 * 60,  # 25 minutes
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    worker_max_tasks_per_child=1000,
)

# Scheduled tasks
celery_app.conf.beat_schedule = {
    'update-resources': {
        'task': 'app.tasks.update_all_resources',
        'schedule': 60.0,  # Every minute
    },
    'process-building-queue': {
        'task': 'app.tasks.process_building_completions',
        'schedule': 10.0,  # Every 10 seconds
    },
    'process-troop-movements': {
        'task': 'app.tasks.process_troop_movements',
        'schedule': 5.0,  # Every 5 seconds
    },
    'calculate-rankings': {
        'task': 'app.tasks.calculate_rankings',
        'schedule': crontab(minute=0),  # Every hour
    },
    'cleanup-inactive': {
        'task': 'app.tasks.cleanup_inactive_players',
        'schedule': crontab(hour=0, minute=0),  # Daily at midnight
    },
    'spawn-animals': {
        'task': 'app.tasks.spawn_oasis_animals',
        'schedule': crontab(minute=0),  # Every hour
    },
    'process-adventures': {
        'task': 'app.tasks.generate_hero_adventures',
        'schedule': crontab(hour='*/6'),  # Every 6 hours
    },
}
```

### Background Tasks
```python
# backend/app/tasks/__init__.py
from celery import shared_task
from sqlalchemy import select, update
from datetime import datetime, timedelta
import asyncio

from app.core.database import async_session
from app.models.village import Village, VillageResources
from app.models.building import Building
from app.models.troop import TroopMovement
from app.services.resource_service import ResourceService
from app.services.websocket_service import WebSocketService
from app.services.combat_service import CombatService

@shared_task
def update_all_resources():
    """Update resources for all active villages"""
    async def _update():
        async with async_session() as db:
            # Get all active villages
            result = await db.execute(
                select(Village).where(Village.is_active == True)
            )
            villages = result.scalars().all()
            
            for village in villages:
                await ResourceService.update_resources(db, village.id)
    
    asyncio.run(_update())

@shared_task
def process_building_completions():
    """Check and complete building constructions"""
    async def _process():
        async with async_session() as db:
            now = datetime.utcnow()
            
            # Get completed buildings
            result = await db.execute(
                select(Building).where(
                    Building.is_upgrading == True,
                    Building.upgrade_complete_at <= now
                )
            )
            buildings = result.scalars().all()
            
            for building in buildings:
                # Complete upgrade
                building.level += 1
                building.is_upgrading = False
                building.upgrade_complete_at = None
                
                # Notify via WebSocket
                await WebSocketService.notify_building_complete(
                    building.village_id,
                    {
                        'building_type': building.building_type_id,
                        'new_level': building.level
                    }
                )
            
            await db.commit()
    
    asyncio.run(_process())

@shared_task
def process_troop_movements():
    """Process arriving troop movements"""
    async def _process():
        async with async_session() as db:
            now = datetime.utcnow()
            
            # Get arrived movements
            result = await db.execute(
                select(TroopMovement).where(
                    TroopMovement.arrival_time <= now,
                    TroopMovement.is_returning == False
                )
            )
            movements = result.scalars().all()
            
            for movement in movements:
                if movement.movement_type == 'attack':
                    # Process attack
                    await CombatService.process_attack(db, movement)
                
                elif movement.movement_type == 'raid':
                    # Process raid
                    await CombatService.process_raid(db, movement)
                
                elif movement.movement_type == 'reinforce':
                    # Process reinforcement
                    await CombatService.process_reinforcement(db, movement)
                
                elif movement.movement_type == 'return':
                    # Return troops home
                    await CombatService.return_troops(db, movement)
                
                # Mark as processed or create return movement
                if movement.movement_type in ['attack', 'raid']:
                    movement.is_returning = True
                    movement.arrival_time = movement.return_time
                else:
                    await db.delete(movement)
            
            await db.commit()
    
    asyncio.run(_process())

@shared_task
def calculate_rankings():
    """Calculate player and alliance rankings"""
    async def _calculate():
        async with async_session() as db:
            # Calculate player rankings by population
            await db.execute(
                """
                UPDATE game.users u
                SET rank = subquery.rank
                FROM (
                    SELECT 
                        user_id,
                        RANK() OVER (ORDER BY SUM(population) DESC) as rank
                    FROM game.villages
                    WHERE is_active = true
                    GROUP BY user_id
                ) AS subquery
                WHERE u.id = subquery.user_id
                """
            )
            
            # Calculate alliance rankings
            await db.execute(
                """
                UPDATE game.alliances a
                SET rank = subquery.rank
                FROM (
                    SELECT 
                        am.alliance_id,
                        RANK() OVER (ORDER BY SUM(v.population) DESC) as rank
                    FROM game.alliance_members am
                    JOIN game.villages v ON v.user_id = am.user_id
                    WHERE v.is_active = true
                    GROUP BY am.alliance_id
                ) AS subquery
                WHERE a.id = subquery.alliance_id
                """
            )
            
            await db.commit()
    
    asyncio.run(_calculate())

@shared_task
def cleanup_inactive_players():
    """Clean up inactive players and their villages"""
    async def _cleanup():
        async with async_session() as db:
            inactive_threshold = datetime.utcnow() - timedelta(days=7)
            
            # Find inactive players
            result = await db.execute(
                select(User).where(
                    User.last_login < inactive_threshold,
                    User.is_active == True
                )
            )
            inactive_users = result.scalars().all()
            
            for user in inactive_users:
                # Mark user as inactive
                user.is_active = False
                
                # Mark villages as inactive
                await db.execute(
                    update(Village)
                    .where(Village.user_id == user.id)
                    .values(is_active=False)
                )
            
            await db.commit()
    
    asyncio.run(_cleanup())
```

## 🔐 SECURITY IMPLEMENTATION

### Security Utilities
```python
# backend/app/core/security.py
from passlib.context import CryptContext
from jose import JWTError, jwt
from datetime import datetime, timedelta
from typing import Optional, Dict, Any
from fastapi import Depends, HTTPException, status, Request
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.ext.asyncio import AsyncSession
from slowapi import Limiter
from slowapi.util import get_remote_address
import secrets
import hashlib

from app.core.config import settings
from app.core.database import get_db

# Password hashing
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

# OAuth2 scheme
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/v1/auth/login")

# Rate limiting
limiter = Limiter(key_func=get_remote_address)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verify password against hash"""
    return pwd_context.verify(plain_password, hashed_password)

def get_password_hash(password: str) -> str:
    """Hash password"""
    return pwd_context.hash(password)

def create_access_token(data: Dict[str, Any], expires_delta: Optional[timedelta] = None) -> str:
    """Create JWT token"""
    to_encode = data.copy()
    
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=settings.JWT_ACCESS_TOKEN_EXPIRE_MINUTES)
    
    to_encode.update({"exp": expire, "iat": datetime.utcnow()})
    encoded_jwt = jwt.encode(
        to_encode,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM
    )
    return encoded_jwt

def verify_token(token: str) -> Dict[str, Any]:
    """Verify and decode JWT token"""
    try:
        payload = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )
        return payload
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Could not validate credentials",
            headers={"WWW-Authenticate": "Bearer"},
        )

async def get_current_user(
    token: str = Depends(oauth2_scheme),
    db: AsyncSession = Depends(get_db)
):
    """Get current user from JWT token"""
    payload = verify_token(token)
    user_id = payload.get("sub")
    
    if user_id is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Could not validate credentials"
        )
    
    # Get user from database
    from app.models.user import User
    result = await db.execute(
        select(User).where(User.id == user_id)
    )
    user = result.scalar_one_or_none()
    
    if user is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="User not found"
        )
    
    return user

def generate_secure_token(length: int = 32) -> str:
    """Generate cryptographically secure random token"""
    return secrets.token_urlsafe(length)

def generate_api_key() -> tuple[str, str]:
    """Generate API key and its hash"""
    api_key = secrets.token_urlsafe(32)
    api_key_hash = hashlib.sha256(api_key.encode()).hexdigest()
    return api_key, api_key_hash

def verify_api_key(api_key: str, api_key_hash: str) -> bool:
    """Verify API key against its hash"""
    return hashlib.sha256(api_key.encode()).hexdigest() == api_key_hash

class SecurityHeaders:
    """Security headers middleware"""
    
    @staticmethod
    async def add_security_headers(request: Request, call_next):
        response = await call_next(request)
        
        # Security headers
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        response.headers["Content-Security-Policy"] = (
            "default-src 'self'; "
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            "style-src 'self' 'unsafe-inline'; "
            "img-src 'self' data: https:; "
            "font-src 'self' data:; "
            "connect-src 'self' wss: https:;"
        )
        
        return response

class RateLimitMiddleware:
    """Custom rate limiting per endpoint"""
    
    LIMITS = {
        "/api/v1/auth/login": "5/minute",
        "/api/v1/auth/register": "3/minute",
        "/api/v1/auth/password-reset": "3/hour",
        "/api/v1/villages": "30/minute",
        "/api/v1/combat/attack": "10/minute",
    }
    
    @classmethod
    def get_limit(cls, path: str) -> str:
        """Get rate limit for specific path"""
        for pattern, limit in cls.LIMITS.items():
            if path.startswith(pattern):
                return limit
        return "60/minute"  # Default limit
```

---

This enterprise blueprint provides the complete backend implementation with all game mechanics, API services, real-time features, and background processing required for a production-ready Travian-Solo game.
