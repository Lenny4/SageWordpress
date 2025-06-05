// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import { FormInterface } from "../../../../../interface/InputInterface";
import { DividerText } from "../../../DividerText";
import {
  createFormContent,
  handleFormIsValid,
  transformOptionsObject,
} from "../../../../../functions/form";
import { FormContentComponent } from "../../FormContentComponent";
import { FormSelect } from "../../fields/FormSelect";
import { FormInput } from "../../fields/FormInput";
import { PPreferenceInterface } from "../../../../../interface/PPreferenceInterface";
import {numberValidator, stringValidator} from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);
const pPreference: PPreferenceInterface = JSON.parse(
  $(`[data-${TOKEN}-ppreference]`).attr(`data-${TOKEN}-ppreference`) ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const isNew = !arRef;

export const ArticleLogistiqueComponent = React.forwardRef((props, ref) => {
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
            Dom: (
              <DividerText
                textAlign="left"
                text={<h2>{translations.words.features}</h2>}
              />
            ),
          },
          {
            fields: [
              {
                name: "arUnitePoids",
                DomField: FormSelect,
                readOnly: !isNew, // todo which configuration make it disabled ?
                options: transformOptionsObject(
                  translations.fArticles.arUnitePoids.values,
                ),
                initValues: {
                  value: getSageMetadata(
                    "arUnitePoids",
                    articleMeta,
                    pPreference.prUnitePoids,
                  ),
                },
              },
              {
                name: "arPoidsNet",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arPoidsNet", articleMeta),
                  validator: {
                    functionName: numberValidator,
                    params: {
                      positive: true,
                    },
                  },
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arCodeBarre",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arCodeBarre", articleMeta),
                  validator: {
                    functionName: stringValidator,
                    params: {
                      maxLength: 19,
                      isReference: true,
                    },
                  }
                },
              },
              {
                name: "arPoidsBrut",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arPoidsBrut", articleMeta),
                  validator: {
                    functionName: numberValidator,
                    params: {
                      positive: true,
                    },
                  },
                },
              },
            ],
          },
        ],
      }),
    };
  };

  const [form] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
