FORMAT: 1A
HOST: https://syrup.keboola.com/ex-fb-ads

# Facebook Ads Extractor configuration
---
Uses [Ads Insights API v2.3](https://developers.facebook.com/docs/marketing-api/insights/v2.3)

# Authentication
POST/GET on /oauth endpoint
Must contain *token* and *config* parameters, where *config* is a table(must already exist) name of the particular configuration.

# Config table
## Attributes

- OAuth information generated by /oauth endpoint
- **api_version**: Use to set the Ads API version to use (default: `v2.4`)

## Data
- Columns:
    - **endpoint**(required): The API endpoint
    - **params**: Query parameters of the api call, JSON encoded
        - Each parameter in the JSON encoded object may either contain a string, eg: `{""date_preset"": ""last_28_days""}`
        - For calls that support "start_time" and "end_time", adiditonal parameter `"slice_by":"day"` can be used to pull data for each day individually
        - If "slice_by" parameter is used, `"running_totals":true` will use static "start_time" for each "slice", only moving the end_time
        - **"sliding_window"**:"7 days" can be used ("7 days" can be replaced by any string supported by [strtotime](http://php.net/manual/en/function.strtotime.php)) with "slice_by" to return a summary of such time period. It is mutually exclusive with "running_totals" (!)
    - **dataType**: Type of data returned by the endpoint. It also describes a table name, where the results will be stored
    - **dataField**: Allows to override which field of the response will be exported. Only used with **data** API
        - If there's multiple arrays in the response "root" the extractor may not know which array to export and fail
        - If the response is an array, the whole response is used by default
        - If there's no array within the root, the path to response data **must** be specified in *dataField*
        - Can contain a path to nested value, dot separater (eg `result.results.products`)
    - **rowId**(required): An unique identificator of the configuration row

### Example data

    "endpoint","params","dataType","dataField","recursionParams","rowId"
    "act_123456789123456/reportstats","{
    ""date_preset"":""last_28_days"",
    ""data_columns"":""['account_id','total_actions','spend']""
    }","","","","1"
    "act_123456789123456/adcampaign_groups","{
    ""fields"":""id,account_id,objective,name,campaign_group_status,buying_type""
    }","","","","2"
    "act_123456789123456/adcampaigns","{
    ""fields"":""id,name,account_id,bid_type,bid_info,campaign_group_id,start_time,end_time,updated_time,created_time,daily_budget,lifetime_budget,budget_remaining,targetinglimit""
    }","","","","3"
    "act_123456789123456/adgroups","{
    ""fields"":""id,account_id,campaign_id,campaign_group_id,name,created_time,targeting,creative_ids,bid_type""
    }","","","","4"
    "act_123456789123456/adcreatives","{
    ""fields"":""id,name,title,body,object_id,object_type,object_story_id,action_spec,link_url,image_url""
    }","","","","5"
    "act_123456789123456/adgroupstats","{
    ""stats_mode"":""with_delivery""
    }","","","","6"

# Group API

## Extractor run [/run]

### Run extraction [POST]

JSON Parameters:

- **config** (required) ... configuration id (name of configuration table)

+ Request (application/json)

    + Headers

            Accept: application/json
            X-StorageApi-Token: Your-Sapi-Token

    + Body

            {
                "config": "main"
            }

    + Schema

            {
                "type": "object",
                "required": true,
                "properties": {
                    "config": {
                        "type": "string",
                        "required": true
                    }
                }
            }

+ Response 201 (application/json)

        {
            "id": "48419532",
            "url": "https://syrup.keboola.com/queue/job/48419532",
            "status": "waiting"
        }


## Generate OAuth token [/oauth{?token,config}]

### Generate token from a web form/UI [POST]

+ Parameters
    + token = `` (required, string, `305-78945-rg48re4g86g48gwgr48e6`) ... Your KBC Token

    + config = `` (required, string, `main`) ... Config table name / configuration ID

+ Request (multipart/form-data; boundary=----WebKitFormBoundaryC5GD12ZfR1D8yZIt)
    + Body

            ------WebKitFormBoundaryC5GD12ZfR1D8yZIt
            Content-Disposition: form-data; name="token"

            305-78954-d54f6ew4f84ew6f48ewq4f684q
            ------WebKitFormBoundaryC5GD12ZfR1D8yZIt--

            ------WebKitFormBoundaryC5GD12ZfR1D8yZIt
            Content-Disposition: form-data; name="config"

            main
            ------WebKitFormBoundaryC5GD12ZfR1D8yZIt--

    + Schema

            {
                "type": "object",
                "required": true,
                "properties": {
                    "config": {
                        "type": "string",
                        "required": true
                    }
                    "token": {
                        "type": "string",
                        "required": true
                    }
                }
            }

+ Response 201 (application/json)

        {
            "status": "ok"
        }

### Generate token manually [GET]

+ Parameters
    + token = `` (required, string, `305-78945-rg48re4g86g48gwgr48e6`) ... Your KBC Token

    + config = `` (required, string, `main`) ... Config table name / configuration ID

+ Response 201 (application/json)

        {
            "TODO"
        }
