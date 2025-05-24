// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import {
  FormInterface,
  InputInterface,
  TableLineInterface,
} from "../../../interface/InputInterface";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getSageMetadata } from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArticleClientInterface } from "../../../interface/FArticleInterface";

let translations: any = getTranslations();

const articleMeta = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const pCattarifs: any[] = JSON.parse(
  $("[data-sage-pcattarifs]").attr("data-sage-pcattarifs") ?? "[]",
);

export const ArticleCatTarifComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const [form] = React.useState<FormInterface>(() => {
    const fArtclients: FArticleClientInterface[] = getSageMetadata(
      "fArtclients",
      articleMeta,
      true,
    );
    const prefix = "fArtclients";
    const lines: TableLineInterface[][] = fArtclients.map(
      (p): TableLineInterface[] => {
        return [
          {
            Dom: <>{pCattarifs[p.acCategorie].ctIntitule}</>,
          },
          {
            field: {
              name: prefix + ".acCoef[" + p.acCategorie + "]",
              DomField: FormInput,
              type: "number",
              hideLabel: true,
            },
          },
          {
            field: {
              name: prefix + ".acPrixVen[" + p.acCategorie + "]",
              DomField: FormInput,
              type: "number",
              hideLabel: true,
            },
          },
          {
            field: {
              name: prefix + ".acRemise[" + p.acCategorie + "]",
              DomField: FormInput,
              type: "number",
              hideLabel: true,
            },
          },
        ];
      },
    );
    return {
      content: [
        {
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
              table: {
                headers: [
                  translations.words.category,
                  translations.words.coefficient,
                  translations.words.sellPrice,
                  translations.words.discount,
                ],
                lines: lines,
              },
            },
          ],
        },
      ],
    };
  });

  const [flatFields] = React.useState(getFlatFields(form));
  const [fieldNames] = React.useState(flatFields.map((f) => f.name));

  type FieldKeys = (typeof fieldNames)[number];

  interface FormState extends Record<FieldKeys, InputInterface> {}

  const getDefaultValue = (): FormState => {
    const fieldValues = flatFields.reduce(
      (acc, field) => {
        acc[field.name] = {
          value: getSageMetadata(field.name, articleMeta) ?? "",
          validator: field.validator,
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      ...fieldValues,
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
