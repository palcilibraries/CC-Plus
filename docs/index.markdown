---
title: CC-PLUS Home
layout: default
nav_order: 1
---

# CC-PLUS
CC­-PLUS is an open source software, community, and administrative tool set for usage statistics management that will support libraries and consortia in data­-driven decisions and effective stewardship of electronic resources.

This shareable platform enables consortia and member libraries to:

* establish and enhance proactive, community-­based approaches to usage data management, especially among consortia in North America, with global applicability;
* create staffing and cost efficiencies with flexible, shared infrastructure;
* increase libraries’ analytic capacity with flexible tools;
* support adherence to and use of COUNTER and NISO standards within the library, publisher, and consortial communities; and
* empower libraries and consortia to practice exemplary stewardship by making data­-informed decisions regarding investments in electronic resources.

The initial release of the CC­-PLUS platform is available under an Apache 2.0 software license. The application uses library SUSHI credentials to harvest COUNTER reports from major scholarly publishers. Reports are validated and stored and the system provides alerts for problems with data harvests. Usage data is available through a dynamic interface corresponding to consortial and library needs.

CC-Plus is currently designed to run as a standalone web-based Laravel application connected to a MySQL database and a web server. It allows for multiple, or just a single, consortia to be managed within a host system. The report harvesting system uses the SUSHI protocol, and expects to receive valid and conformant COUNTER-5 usage reports.

Once the application is installed, the application administrator will need to configure the membership of the consortia, users who will be using the data and their roles, and the report providers. The schedule of data harvesting is also configurable and involves validation and storage in the database. The raw data can be saved as it is received as JSON (encrypted in the filesystem). Harvested data stored in the database can then be queried to build, display, and/or download faceted reports.

The code repository and this documentation can be downloaded at: [http://github.com/palcilibraries/CC-PLUS](http://github.com/palcilibraries/cc-plus).


This project has been generously supported by funding from the Institute of Museum and Library Services (IMLS) and the vision, time, and expertise of mulitple consortia and volunteers across North America and Europe. Full details the project partners, funding and objectives can be found here: [http://cc-plus.org](http://cc-plus.org).