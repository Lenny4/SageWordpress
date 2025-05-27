// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React from "react";
import { getTranslations } from "../../functions/translations";
import Box from "@mui/material/Box";
import { ArticleTab1Component } from "./tab1/ArticleTab1Component";
import { TabInterface } from "../../interface/TabInterface";
import { TabsComponent } from "../component/tab/TabsComponent";
import { ArticleTab2Component } from "./tab2/ArticleTab2Component";
import { ArticleTab3Component } from "./tab3/ArticleTab3Component";
import { ArticleTab4Component } from "./tab4/ArticleTab4Component";

const containerSelector = "#sage_product_data";
let translations: any = getTranslations();

const formSelector = "form[name='post']";
const selectProductTypeSelector = formSelector + " select[name='product-type']";

export const ArticleComponent = () => {
  const [tabs] = React.useState<TabInterface[]>(() => {
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
      {isSageProductType && <TabsComponent tabs={tabs} />}
    </Box>
  );
};

const dom = document.querySelector(containerSelector);
if (dom) {
  const root = createRoot(dom);
  root.render(<ArticleComponent />);
}
