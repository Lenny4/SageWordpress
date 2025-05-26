// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import { getTranslations } from "../../../functions/translations";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import {
  FormInterface,
  InputInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../functions/getMetadata";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import {
  FArtglosseInterface,
  FGlossaireInterface,
} from "../../../interface/FArticleInterface";
import { FormInput } from "../../component/form/FormInput";
import { Tooltip } from "@mui/material";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fGlossaires: FGlossaireInterface[] = JSON.parse(
  $("[data-sage-fglossaires]").attr("data-sage-fglossaires") ?? "[]",
);

export const ArticleGlossairesComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const getForm = (
    fArtglosses: FArtglosseInterface[] | undefined = undefined,
  ): FormInterface => {
    const prefix = "fArtglosses";
    fArtglosses ??= getListObjectSageMetadata(prefix, articleMeta, "glNo");
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
                headers: ["", translations.words.intitule, ""],
                fullWidth: true,
                canDelete: true,
                add: {
                  table: {
                    headers: [translations.words.intitule],
                    key: "glNo",
                    search: (item: FGlossaireInterface, search: string) => {
                      return (
                        item.glIntitule
                          .toLowerCase()
                          .includes(search.toLowerCase()) ||
                        item.glText.toLowerCase().includes(search.toLowerCase())
                      );
                    },
                    items: fGlossaires
                      .filter(
                        (fGlossaire) =>
                          fArtglosses.find(
                            (fArtglosse) =>
                              fArtglosse.glNo.toString() ===
                              fGlossaire.glNo.toString(),
                          ) === undefined,
                      )
                      .map((fGlossaire): TableLineItemInterface => {
                        return {
                          item: fGlossaire,
                          lines: [
                            {
                              Dom: <span>{fGlossaire.glIntitule}</span>,
                            },
                            {
                              Dom: (
                                <Tooltip title={fGlossaire.glText} arrow>
                                  <p>
                                    {fGlossaire.glText.length > 102
                                      ? fGlossaire.glText.slice(0, 102) + "..."
                                      : fGlossaire.glText}
                                  </p>
                                </Tooltip>
                              ),
                            },
                          ],
                        };
                      }),
                  },
                },
                items: fArtglosses.map((fArtglosse): TableLineItemInterface => {
                  const fGlossaire = fGlossaires.find(
                    (f) => f.glNo.toString() === fArtglosse.glNo.toString(),
                  );
                  return {
                    item: fGlossaire,
                    lines: [
                      {
                        field: {
                          name: prefix + "[" + fGlossaire.glNo + "].glNo",
                          DomField: FormInput,
                          type: "hidden",
                          hideLabel: true,
                        },
                      },
                      {
                        Dom: <span>{fGlossaire.glIntitule}</span>,
                      },
                      {
                        Dom: (
                          <Tooltip title={fGlossaire.glText} arrow>
                            <p>
                              {fGlossaire.glText.length > 102
                                ? fGlossaire.glText.slice(0, 102) + "..."
                                : fGlossaire.glText}
                            </p>
                          </Tooltip>
                        ),
                      },
                    ],
                  };
                }),
              },
            },
          ],
        },
      ],
    };
  };

  const [form, setForm] = React.useState<FormInterface>(getForm());
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
      getForm={getForm}
      setForm={setForm}
    />
  );
});
