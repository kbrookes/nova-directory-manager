# Nova Directory Manager 2.0 - Upgrade Summary

## Recent Updates (v2.0.15–v2.0.20)

- **ACF Field Group Auto-Injection:**
  - Category selector (multi-select, required) is now injected into the Offers form programmatically.
- **Advertiser Type Taxonomy:**
  - Custom taxonomy auto-assigned: 'Advertiser' for advertisers, 'YBA Member' for business owners.
- **Author Support for Offers:**
  - Offers now have proper author assignment and admin UI support.
- **Settings Tab for Admin Notification Emails:**
  - New admin UI for adding/removing notification emails, stored in `ndm_admin_emails`.

## Features Remaining / To Be Implemented

- **Payment Integration:**
  - Stripe/Fluent Forms payment for paid offers (Advertisers and extra Business Owner offers)
- **Offer Approval Workflow:**
  - Admin approval queue, status management, and notification triggers
- **Offer Expiry/Auto-Unpublish:**
  - Automatic expiry and unpublishing of offers based on end date
- **Admin Notification Triggers:**
  - Use the new admin email list for notifications (new member, new offer, approval needed, etc.)
- **Public Offer Listing & Filtering:**
  - Shortcodes or templates for public offer display, search, and filtering
- **Reporting & Analytics:**
  - Admin dashboard for offer stats, payments, user activity
- **Frontend Polish:**
  - Improved frontend UI/UX for offer/business forms and dashboards
- **Documentation:**
  - Update all docs for new features, workflows, and shortcodes

## Overview

Nova Directory Manager 2.0 adds a comprehensive offers and advertising system, transforming the plugin into a complete business promotion platform with payment processing capabilities.

## Key New Features

### 1. User Role System
- **Business Owners**: Get X free offers with directory membership, pay for additional offers
- **Advertisers**: New role that pays for all offers, can create offers for any business

### 2. Offers Post Type
- Complete ACF field structure (see `docs/acf-export-225.json`)
- Business selection, descriptions, timing, publishing controls
- Image uploads, offer types (BOGOF, Loyalty, Discount, etc.)

### 3. Pricing & Payment System
- **Volume Pricing**: Single offers, 5 offers, 10 offers with different durations
- **Duration Options**: 1 month, 3 months, 6 months, 12 months
- **Stripe Integration**: Payment processing via Fluent Forms
- **Role-Based Pricing**: Different pricing for business owners vs advertisers

### 4. Workflow Management
- **Draft → Pending → Approved → Published → Expired**
- **Admin Approval**: All offers require admin approval before publishing
- **Automatic Expiry**: Offers automatically unpublish on end date
- **Payment Tracking**: Monitor paid vs unpaid offers

### 5. Admin Interface
- **Tabbed System**: Directory tab (existing) + Offers tab (new)
- **Pricing Configuration**: Set pricing for both user roles
- **Offer Management**: Approve/reject pending offers
- **Payment Tracking**: Monitor payment status and expiry

### 6. Frontend Features
- **Business Owner Dashboard**: Create/edit offers for their businesses
- **Advertiser Dashboard**: Create offers for any business
- **Public Display**: Offer listings, business integration, search/filter

## Technical Requirements

### New Dependencies
- **ACF Pro**: For offer field management
- **Fluent Forms Pro**: For Stripe payment integration
- **Stripe**: Payment processing

### Database Changes
- New `offers` post type
- Custom tables for pricing configuration
- Payment tracking tables
- User role and permission tables

### New Shortcodes
- `[ndm_offers_list]` - Display offers
- `[ndm_offer_form]` - Create/edit offer form
- `[ndm_offers_dashboard]` - User dashboard

## Implementation Plan

### Phase 1: Core Infrastructure
1. Create offers post type
2. Import ACF field group
3. Add tabbed admin interface
4 Create advertiser user role

### Phase 2: Pricing & Payment
1. Implement pricing configuration2. Integrate Stripe payments
3. Add payment tracking system
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

## Next Steps

1. **Review Requirements**: Confirm all requirements are understood
2. **Technical Planning**: Detailed technical architecture planning
3. **Development Phases**: Begin Phase 1 implementation
4. **Testing Strategy**: Plan comprehensive testing approach
5**Documentation**: Update all documentation for 2.0 features 