# Reply to the client — HotestBooking_Plugin.pdf (2026-11-11)

**1. Page has to fill the screen (Data import and other pages)**
Done. Every page now uses the full width. Open **System → Data import** to see it.

**2. Room availability is hard to read, too much blue, too compact**
Done. Go to **Reports & data → Room availability**. Each room now shows its status (Occupied, Booked, Free, Maintenance…) and "booked X times", with more space and softer colours, like your old screen.

**3. Reference number is too long (RT-2026-000016)**
Go to **System → Settings → Booking rules → Booking reference**. Set the year to *Short* (26) or *None* and lower the digits (e.g. 3), for example RT-016. Only new bookings use the new format.

**4. Abbreviations (Orange Money → OM, Cash at desk → Cash, Wave)**
Go to **System → Settings → Payments**. Each method has a **Short name** box (OM, Cash, Wave). The dashboard uses the short name.

**5. Orange Money missing from the payment methods report**
Orange Money is set up. **Reports & data → Reports → Sales** only lists methods that have payments in the selected dates. It shows as soon as an Orange Money payment is recorded, as on your French screenshot.

**6. Too much blue on the report icons**
Done. The cards in **Reports & data → Reports** now use different colours instead of all blue.

**7. Payroll "Pay" setup**
Go to **Staff & access → Staff**, open an employee, then the **Pay** tab:
- Overtime, paid time off, unpaid time off, sick pay: choose **per hour** or **per day**.
- Vacation pay and gratification: **percentage** or **amount**.
- Bonus and commission: an **amount or percentage** box.
- Transport: an **amount** box (e.g. 30 000 FCFA).
- Seniority: 1% per full year from the hiring date, added on each work anniversary.
- Earnings that count: tick regular pay, overtime, bonus, commission, transport, gratification, seniority.
- **Additions**: next to Deductions and garnishments, for custom lines on the paycheck.

**8. Printable work schedule with all employees**
Go to **Staff & access → Payroll → Work shifts**, pick week or month in **Show**, then click **Print** (or **PDF**). Each employee needs a shift assigned to appear on it.

**9. Missing "Actions" title**
Done. The last column on **Dashboard** and **Front desk → Bookings** now has the **Action** title.

**10. Payment method on the QR code receipt**
Done. The booking page the QR code opens now lists each payment with its date, method, reference and amount.

**11. Check in / check out from the QR code**
Scan the guest's QR code while signed in, or click **Scan** at the top of the dashboard. A quick card opens with **Check in**, **Check out** and **Record payment**. Guests scanning it still see only their booking.

**12. Phone number and email on the receipt**
The phone is entered in **System → Settings → General → Phone**: (225) 07 88 15 43 02. Receipts now show the phone and email.

**13. Payment reference for each payment method**
Go to **System → Settings → Payments**. For each method set **Reference** to *Not asked*, *Optional* or *Required*, and its label. Wave and Orange Money are set to *Required* ("Transaction ID"). You can search by reference in **Front desk → Payments**.

**14. Cash register (Open / Close)**
Go to **Front desk → Cash register** (also a card on the **Dashboard**). Click **Open** at the start of the shift. Payments are totalled per method (Wave, OM, Cash). Click **Close** at the end, count the cash and see any difference, then **Print** the slip. To make staff open a register before taking payments, use **System → Settings → Cash register**. Give staff only the cash register in **Staff & access → Permissions**, so they don't need other areas.

Thank you for the kind words, we're glad you like the new features!
