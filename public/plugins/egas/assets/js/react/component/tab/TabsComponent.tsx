import React from "react";
import Box from "@mui/material/Box";
import { Tab, Tabs } from "@mui/material";
import { CustomTabPanel } from "./CustomTabPanel";
import { FormTabInterface } from "../../../interface/InputInterface";
import { TOKEN } from "../../../token";

type State = {
  tabs: FormTabInterface;
};

export const TabsComponent = ({ tabs }: State) => {
  const [tabValue, setValue] = React.useState(
    Number(tabs.tabProps?.defaultValue ?? 0),
  );

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  React.useEffect(() => {
    const handler = (e: any) => {
      setValue(Number(e.detail));
    };
    window.addEventListener(`${TOKEN}-tabpanel-${tabs.id}`, handler);
    return () => {
      window.removeEventListener(`${TOKEN}-tabpanel-${tabs.id}`, handler);
    };
  }, [tabs.id]);

  return (
    <>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs
          {...tabs.tabProps}
          value={tabValue}
          onChange={handleChange}
          variant="scrollable"
          scrollButtons="auto"
          aria-label="scrollable auto tabs"
        >
          {tabs.tabs.map((tab, index) => (
            <Tab label={tab.label} key={index} />
          ))}
        </Tabs>
      </Box>
      {tabs.tabs.map((tab, index) => (
        <CustomTabPanel value={tabValue} index={index} key={index} id={tabs.id}>
          {tab.dom}
        </CustomTabPanel>
      ))}
    </>
  );
};
