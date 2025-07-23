# Nova Directory Manager 2.0 System Requirements

## Overview

Nova Directory Manager 2.0 introduces a comprehensive offers and advertising system that allows businesses and advertisers to create, manage, and pay for promotional offers. This upgrade transforms the plugin from a simple directory manager into a complete business promotion platform.

## Core Features

### 1. User Role System

#### Business Owner Role (Existing)
- **Included Offers**: X number of free offers as part of directory membership
- **Additional Offers**: Pay-per-offer pricing for extra promotions
- **Access**: Can create offers for their own businesses only

#### Advertiser Role (New)
- **Paid Offers Only**: All offers require payment
- **Flexible Pricing**: Volume and duration-based pricing
- **Access**: Can create offers for any business (with permission)

### 2. Offers Post Type

#### ACF Field Structure
Based on the provided ACF export (`docs/acf-export-2025-17`), offers include:

**Business Selection**
- `offer_business` - Post object field linking to business post type
- Only shows businesses owned by current user (for business owners)
- Allows selection of any business (for advertisers)

**Description Fields**
- `offer_short_description` - Preview text (max 50 words)
- `offer_long_description` - Full WYSIWYG description
- `offer_image` - Image upload (JPG, PNG, GIF, max 1MB)

**Offer Details**
- `offer_type` - Select: BOGOF, Loyalty, Discount, Introductory, Other
- `offer_discount_value` - Percentage off (conditional on discount type)
- `offer_loyalty_details` - Loyalty program details
- `offer_type_other` - Custom offer type description

**Timing & Scheduling**
- `offer_timing` - Select: Fixed dates, Days of week, Until end date, Ongoing
- `offer_start_date` - When offer becomes available
- `offer_end_date` - When offer expires
- `offer_days_of_week` - Specific days (Monday-Sunday)
- `offer_ongoing` - Checkbox for ongoing offers

**Publishing Control**
- `offer_publishing_dates` - Same as offer or different dates
- `offer_publish_start` - When ad starts displaying
- `publish_offer_off` - When ad stops displaying

**Additional Fields**
- `offer_redeem` - How to redeem the offer
- `offer_eligibility` - Terms & conditions

### 3. Pricing System

#### Volume Pricing Structure
```
Single Offer:
- 1 month: $50
- 3s: $120
-6 months: $200
- 12hs: $400 Offers:
-1h: $100
- 3s: $250
-6 months: $400
-12s: $80 Offers:
-1h: $180
- 3s: $450
-6 months: $720 12nths: $1,440`

#### Role-Based Pricing
- **Business Owners**: Customizable pricing for additional offers beyond included quota
- **Advertisers**: Customizable pricing for all offers
- **Admin Configurable**: All pricing tiers editable in admin panel

### 4. Payment Integration

#### Stripe Integration via Fluent Forms
- **Payment Processing**: Stripe payment gateway integration
- **Form Integration**: Fluent Forms handles payment collection
- **Automatic Expiry**: Offers automatically expire based on duration
- **Payment Status Tracking**: Track paid vs unpaid offers

### 5. Workflow Management

#### Offer Creation Process1User submits offer** via Fluent Form
2 **Offer created** in draft status
3. **Payment processed** (if required)
4. **Admin approval** required for publication
5. **Offer published** on scheduled date
6 **Offer expires** automatically on end date

#### Status Management
- **Draft**: Initial state, pending admin approval
- **Pending**: Payment received, awaiting admin approval
- **Approved**: Ready for publication
- **Published**: Live on website
- **Expired**: Past end date, automatically unpublished
- **Rejected**: Admin rejected, requires resubmission

### 6. Admin Interface Admin Interface

#### Tabbed Administration
- **Directory Tab**: Existing business directory management
- **Offers Tab**: New offers system management

#### Offers Tab Features
- **Pricing Configuration**: Set pricing for both user roles
- **Offer Management**: Approve/reject pending offers
- **Payment Tracking**: Monitor payment status
- **Expiry Management**: View and manage expiring offers
- **User Management**: Manage business owners and advertisers

### 7. Frontend Features

#### Business Owner Dashboard
- **Offer Creation**: Create new offers for their businesses
- **Offer Management**: Edit existing offers
- **Payment History**: View payment records
- **Usage Tracking**: See included vs paid offers

#### Advertiser Dashboard
- **Offer Creation**: Create offers for any business
- **Payment Management**: Handle all payments
- **Offer Tracking**: Monitor offer performance

#### Public Display
- **Offer Listings**: Display active offers
- **Business Integration**: Show offers on business pages
- **Search & Filter**: Find offers by category, location, etc.

## Technical Requirements

### Database Changes
- New `offers` post type
- Custom tables for pricing configuration
- Payment tracking tables
- User role and permission tables

### Plugin Dependencies
- **ACF Pro**: Required for offer field management
- **Fluent Forms**: Required for offer creation and payment
- **Fluent Forms Pro**: Required for Stripe integration
- **Stripe**: Payment processing

### Custom Hooks & Filters
- `ndm_offer_created` - Fired when offer is created
- `ndm_offer_approved` - Fired when offer is approved
- `ndm_offer_expired` - Fired when offer expires
- `ndm_payment_received` - Fired when payment is processed

### Shortcodes
- `[ndm_offers_list]` - Display offers
- `[ndm_offer_form]` - Create/edit offer form
- `[ndm_offers_dashboard]` - User dashboard

## Implementation Phases

### Phase 1: Core Infrastructure
1. Create offers post type
2. Import ACF field group
3. Add tabbed admin interface
4 Create advertiser user role

### Phase 2: Pricing & Payment
1. Implement pricing configuration2. Integrate Stripe payments3e payment tracking system
4. Add automatic expiry functionality

### Phase 3: Frontend Development
1. Create offer creation forms
2ld user dashboards
3ment offer display system4arch and filtering

### Phase 4: Admin Management
1. Build offer approval system2payment management interface
3. Add reporting and analytics4nt user management

### Phase 5: Testing & Optimization
1. Comprehensive testing
2. Performance optimization
3. Security review
4. Documentation completion

## Success Metrics

### User Adoption
- Number of business owners creating offers
- Number of advertisers signing up
- Offer creation frequency

### Revenue Generation
- Payment processing volume
- Revenue per user role
- Pricing tier utilization

### System Performance
- Offer approval time
- Payment processing success rate
- System uptime and reliability

## Risk Mitigation

### Technical Risks
- **Payment Integration**: Thorough testing with Stripe sandbox
- **Performance**: Database optimization for large offer volumes
- **Security**: Payment data protection and user permission validation

### Business Risks
- **User Adoption**: Clear onboarding and documentation
- **Pricing Strategy**: Flexible pricing configuration
- **Support Load**: Comprehensive help documentation and support system

## Future Enhancements

### Advanced Features
- **Analytics Dashboard**: Offer performance metrics
- **A/B Testing**: Offer effectiveness testing
- **Email Marketing**: Automated offer promotion
- **Social Media Integration**: Cross-platform offer sharing
- **Mobile App**: Native mobile offer management

### Integration Opportunities
- **Google My Business**: Sync offers to Google
- **Facebook Business**: Social media offer integration
- **Email Marketing**: Newsletter offer inclusion
- **SMS Marketing**: Text message offer promotion 