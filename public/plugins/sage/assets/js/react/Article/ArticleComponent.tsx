// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React from "react";
import { getTranslations } from "../../functions/translations";
import Box from "@mui/material/Box";
import { CustomTabPanel } from "../component/CustomTabPanel";
import { Tab, Tabs } from "@mui/material";
import { ArticleTab1Component } from "./ArticleTab1Component";
import { ArticleTab2Component } from "./ArticleTab2Component";
import { ArticleTab3Component } from "./ArticleTab3Component";
import { ArticleTab4Component } from "./ArticleTab4Component";

const containerSelector = "#sage_product_data";
let translations: any = getTranslations();

interface ArticleTabs {
  label: string;
  dom: React.ReactNode;
  ref: React.RefObject<any>;
}

const formSelector = "form[name='post']";
const selectProductTypeSelector = formSelector + " select[name='product-type']";

export default function ArticleComponent() {
  const [value, setValue] = React.useState(0);
  const [tabs] = React.useState<ArticleTabs[]>(() => {
    return [
      {
        label: translations.words.identification,
        Component: ArticleTab1Component,
      },
      { label: translations.words.descriptif, Component: ArticleTab2Component },
      { label: translations.words.freeFields, Component: ArticleTab3Component },
      { label: translations.words.settings, Component: ArticleTab4Component },
    ].map(({ label, Component }) => {
      const ref = React.createRef();
      return {
        label,
        dom: <Component ref={ref} />,
        ref,
      };
    });
  });
  const getIsSageProductType = () => {
    return $(selectProductTypeSelector).val() === "sage";
  };
  const [isSageProductType, setIsSageProductType] = React.useState(
    getIsSageProductType(),
  );

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  React.useEffect(() => {
    $(selectProductTypeSelector).on("change", () => {
      setIsSageProductType(getIsSageProductType());
    });
    $(formSelector).on("submit", (e) => {
      let hasError = false;
      for (const tab of tabs) {
        if (tab.ref.current) {
          hasError = hasError || !tab.ref.current.isValid();
        }
      }
      if (hasError) {
        e.preventDefault();
      }
    });
  }, []);

  return (
    <Box sx={{ width: "100%" }}>
      {isSageProductType && (
        <>
          <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
            <Tabs
              value={value}
              onChange={handleChange}
              aria-label="article tabs"
            >
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
        </>
      )}
    </Box>
  );
}

const dom = document.querySelector(containerSelector);
if (dom) {
  const root = createRoot(dom);
  root.render(<ArticleComponent />);
}
