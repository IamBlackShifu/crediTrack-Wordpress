# Financial rules and transitions

All amounts are stored as `DECIMAL(18,2)` and calculated in PHP as integer cents. Rounding is half-up. Calculation method and rate period are disclosed together; an interest rate is not represented as APR. Formulas use `P` principal, `r` entered percentage rate, and `m` term months:

- Flat: `I = P × r / 100`.
- Monthly: `I = P × r / 100 × m`.
- Quarterly: `I = P × r / 100 × m / 3`.
- Annual: `I = P × r / 100 × m / 12`.
- Amortized: annual nominal rate divided by 12, applied each month to declining principal; the level payment uses the ordinary annuity formula and every month's interest is rounded to currency precision.
- `total repayable = P + I`; `outstanding = max(total repayable - valid payments, 0)`.

Equal principal and interest portions are rounded down across installments; all remainder is assigned to the final installment. A due date retains the disbursement day where possible and otherwise clamps to the target month's last day.

The legacy Flat option treats the entered percentage as a whole-contract rate to preserve the specified USD 100 + 15% = USD 115 regression. Monthly, Quarterly, and Annual are precomputed simple-interest rate bases, not separate industry-standard calculation methods. For new conventional installment products, use Amortized. Flat/precomputed products need jurisdiction-specific early-settlement and unearned-interest rules before production use. APR/effective yield must be calculated separately from dated contractual cash flows and applicable fees before being used as a regulatory disclosure.

References: CFPB guidance distinguishes precomputed interest from the more common outstanding-balance simple-interest method and distinguishes interest rate from APR; IFRS 9 uses the effective interest method to allocate interest revenue for amortised-cost instruments. Applicable local lending law overrides product defaults.

Payments allocate to unpaid schedules ordered by due date and ID. Partial allocations mark Partial; fully settled installments mark Paid. Overpayment is rejected. Completion occurs only at zero outstanding. The state transitions are Pending → Approved → Active → Completed, with Pending/Approved/Active → Defaulted → Written Off.
