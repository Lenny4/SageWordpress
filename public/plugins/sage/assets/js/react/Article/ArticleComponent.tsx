// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React from "react";
import { getTranslations } from "../../functions/translations";
import Box from "@mui/material/Box";
import { CustomTabPanel } from "../component/CustomTabPanel";
import { Tab, Tabs } from "@mui/material";
import {ArticleTab1Component} from "./ArticleTab1Component";
import {ArticleTab2Component} from "./ArticleTab2Component";
import {ArticleTab3Component} from "./ArticleTab3Component";
import {ArticleTab4Component} from "./ArticleTab4Component";

const containerSelector = "#sage_product_data";
const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
let translations: any = getTranslations();

interface ArticleTabs {
  label: string;
  dom: React.ReactNode;
  ref: React.RefObject<any>;
}

export default function ArticleComponent() {
  const [value, setValue] = React.useState(0);
  const [tabs] = React.useState<ArticleTabs[]>(() => {
    const refs = [0, 1, 2, 3].map(() => React.createRef());
    return [
      {
        label: translations.words.identification,
        dom: <ArticleTab1Component ref={refs[0]} />,
        ref: refs[0],
      },
      {
        label: translations.words.descriptif,
        dom: <ArticleTab2Component ref={refs[1]} />,
        ref: refs[1],
      },
      {
        label: translations.words.freeFields,
        dom: <ArticleTab3Component ref={refs[2]} />,
        ref: refs[2],
      },
      {
        label: translations.words.settings,
        dom: <ArticleTab4Component ref={refs[3]} />,
        ref: refs[3],
      },
    ];
  });

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  return (
    <Box sx={{ width: "100%" }}>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs value={value} onChange={handleChange} aria-label="article tabs">
          {tabs.map((tab, index) => (
            <Tab label={tab.label} key={index} />
          ))}
        </Tabs>
      </Box>
      {tabs.map((tab, index) => (
        <CustomTabPanel value={value} index={index} key={index}>
          {tab.dom}
        </CustomTabPanel>
      ))}
    </Box>
  );
}

const dom = document.querySelector(containerSelector);
if (dom) {
  const root = createRoot(dom);
  root.render(<ArticleComponent />);
}
