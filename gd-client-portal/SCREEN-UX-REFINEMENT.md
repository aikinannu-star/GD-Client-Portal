# Screen UX Refinement

## Goal

Standardize the administrator experience without replacing working feature logic.

## Shared components

The plugin now provides reusable components for:

- Page headers with concise descriptions
- Primary and secondary actions
- Consistent content sections
- Empty states with clear next actions
- Status badges
- Responsive administration layouts

## Screen refinement rules

### 1. Every screen should answer three questions

1. What is this screen for?
2. What should the administrator do next?
3. What is the current state of the information?

### 2. Prefer action-oriented empty states

Avoid empty tables without explanation. When there is no data, explain why and
provide the primary next action.

### 3. Reduce visual hierarchy conflicts

One screen should normally have:

- One page title
- One primary action
- Optional secondary actions
- Clearly grouped sections

### 4. Preserve working workflows

UX changes should wrap and improve existing screens rather than duplicate or
replace established business logic without regression testing.

## Priority screens for progressive migration

1. Service Intake
2. Onboarding
3. Projects
4. Collaboration
5. Approvals
6. Deliverables
7. Billing
8. Support
