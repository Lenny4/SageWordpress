// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React, { useImperativeHandle } from "react";
import Box from "@mui/material/Box";
import { ArticleTab1Component } from "./tab1/ArticleTab1Component";
import { ArticleTab2Component } from "./tab2/ArticleTab2Component";
import { ArticleTab4Component } from "./tab4/ArticleTab4Component";
import { getTranslations } from "../../../../functions/translations";
import { FormInterface } from "../../../../interface/InputInterface";
import {
  createFormContent,
  handleFormIsValid,
  onSubmitForm,
} from "../../../../functions/form";
import { FormContentComponent } from "../FormContentComponent";
import { TOKEN } from "../../../../token";

const containerSelector = `#${TOKEN}_product_data`;
let translations: any = getTranslations();

const formSelector = "form[name='post']";
const selectProductTypeSelector = formSelector + " select[name='product-type']";
let isValidArticleComponent = false;

export const ArticleComponent = React.forwardRef((props, ref) => {
  const getForm = (): FormInterface => {
    return {
      content: createFormContent({
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            tabs: {
              tabs: [
                {
                  label: translations.words.identification,
                  Component: ArticleTab1Component,
                },
                {
                  label: translations.words.descriptif,
                  Component: ArticleTab2Component,
                },
                // { label: translations.words.freeFields, Component: ArticleTab3Component },
                {
                  label: translations.words.settings,
                  Component: ArticleTab4Component,
                },
              ].map(({ label, Component }) => {
                const ref = React.createRef();
                return {
                  label,
                  dom: <Component ref={ref} />,
                  ref,
                };
              }),
            },
          },
        ],
      }),
    };
  };
  const [form] = React.useState<FormInterface>(getForm());

  const getIsSageProductType = () => {
    return $(selectProductTypeSelector).val() === `${TOKEN}`;
  };
  const [isSageProductType, setIsSageProductType] = React.useState(
    getIsSageProductType(),
  );

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    $(selectProductTypeSelector).on("change", () => {
      setIsSageProductType(getIsSageProductType());
    });
    // region on submit form
    const publishingAction = $(formSelector).find("#publishing-action");
    const span = $(publishingAction).find("span");
    const inputSubmit = $(publishingAction).find('input[type="submit"]');
    onSubmitForm(
      form,
      formSelector,
      isValidArticleComponent,
      () => {
        $(span).addClass("is-active");
        $(inputSubmit).addClass("disabled");
      },
      () => {
        $(span).removeClass("is-active");
        $(inputSubmit).removeClass("disabled");
      },
    );
    // endregion
  }, []);

  return (
    <Box sx={{ width: "100%" }}>
      {isSageProductType && (
        <FormContentComponent content={form.content} transPrefix="fArticles" />
      )}
    </Box>
  );
});

const dom = document.querySelector(containerSelector);
if (dom) {
  const root = createRoot(dom);
  root.render(<ArticleComponent />);
}
