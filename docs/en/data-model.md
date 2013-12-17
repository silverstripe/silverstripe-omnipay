# Data model

Developers have flexibility in choosing how `Payment` DataObjects are connected to their model, but it is recommended that you use a has_many relationship so that you can handle partial payments, and also it means that if one payment fails, then another payment can be made via different means.

Your model you connect payments to will generally be something like: `Bill`, `Invoice`, `Order`, `Donation`, `Registration`

An extesion (`Payable`) has been written to provide the above functionality.
