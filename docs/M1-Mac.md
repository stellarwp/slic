# M1 Mac `tric` Setup

## Prerequisites

1. Docker Desktop For Mac (installed and running)

2. Docker Experimental Features should be disabled. [Check Screenshot](https://drive.google.com/file/d/1ccghYtRqw2mqKUHNHKbC5Y7XjPBQoXz1/view?usp=sharing).

## Setup

The experimental features on Docker for Mac created some permission-related bugs that will hit the tric container. 

### Workaround

1. Download wp core WordPress in the tric/_wordpress directory manually using WP-CLI to have the file modes set up correctly.


